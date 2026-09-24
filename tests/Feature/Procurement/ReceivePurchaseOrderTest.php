<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemTransfer;
use App\Models\Restock;
use App\Models\RestockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Procurement\CreateFollowUpPurchaseOrder;
use App\Services\Procurement\ReceivePurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Inventory\RestockTestCase;

final class ReceivePurchaseOrderTest extends RestockTestCase
{
    private ReceivePurchaseOrder $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReceivePurchaseOrder::class);
    }

    public function test_partial_then_final_receipts_post_exact_linked_inventory_and_status_evidence(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), [
            'current_stock' => '1.000', 'cost_price' => '40.00',
        ]);
        $this->initialize($variant, $actor);
        [$purchaseOrder, $items] = $this->purchaseOrder($actor, [[$variant, '5.000', '55.00']]);

        $first = $this->receive($actor, $purchaseOrder, [
            $this->line($items[0], '2', '60'),
        ], reference: ' DR-26B ', notes: ' First   delivery ');

        $this->assertSame($purchaseOrder->id, $first->purchase_order_id);
        $this->assertSame($actor->id, $first->recorded_by);
        $this->assertSame('DR-26B', $first->reference_text);
        $this->assertSame('First delivery', $first->notes);
        $this->assertSame('120.00', $first->total_cost);
        $this->assertCount(1, $first->items);
        $this->assertSame($items[0]->id, $first->items[0]->purchase_order_item_id);
        $this->assertSame('2.000', $first->items[0]->quantity);
        $this->assertSame('60.00', $first->items[0]->unit_cost);
        $this->assertSame('120.00', $first->items[0]->line_total);
        $this->assertSame(['3.000', '60.00'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame('55.00', $items[0]->fresh()->expected_unit_cost);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->fresh()->status);
        $this->assertSame('3.000', $items[0]->outstandingQuantity());
        $movement = StockMovement::query()->where('restock_item_id', $first->items[0]->id)->sole();
        $this->assertSame(StockMovement::TYPE_RESTOCK, $movement->movement_type);
        $this->assertSame(['1.000', '2.000', '3.000'], [
            $movement->quantity_before, $movement->quantity_change, $movement->quantity_after,
        ]);

        $second = $this->receive($actor, $purchaseOrder, [$this->line($items[0], '3', '62.50')]);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(['6.000', '62.50'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->fresh()->status);
        $this->assertSame('0.000', $items[0]->outstandingQuantity());
        $this->assertSame('5.000', $items[0]->acceptedQuantity());
        $this->assertSame(2, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_direct_full_receipt_completes_and_replays_after_completion_without_duplicate_posting(): void
    {
        $actor = User::factory()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        [$purchaseOrder, $items] = $this->purchaseOrder($actor, [[$variant, '2.000', '10.00']]);
        $token = Str::uuid()->toString();
        $lines = [$this->line($items[0], '2.000', '12.25')];

        $first = $this->receive($actor, $purchaseOrder, $lines, $token, 'Receipt', 'Complete');
        $replay = $this->receive($actor, $purchaseOrder, $lines, strtoupper($token), ' Receipt ', ' Complete ');

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->fresh()->status);
        $this->assertSame('2.000', $variant->fresh()->current_stock);
        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(1, RestockItem::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_multiple_lines_allow_omission_and_fractional_receiving(): void
    {
        $actor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $whole = $this->variant($product, ['size' => 'Whole']);
        $fractional = $this->variant($product, [
            'size' => 'Bulk', 'unit' => 'kg', 'quantity_mode' => 'fractional',
        ]);
        $this->initialize($whole, $actor);
        $this->initialize($fractional, $actor);
        [$purchaseOrder, $items] = $this->purchaseOrder($actor, [
            [$whole, '4.000', '10.00'], [$fractional, '1.250', '20.00'],
        ]);

        $first = $this->receive($actor, $purchaseOrder, [$this->line($items[1], '0.125', '21.50')]);
        $this->assertCount(1, $first->items);
        $this->assertSame('0.000', $whole->fresh()->current_stock);
        $this->assertSame('0.125', $fractional->fresh()->current_stock);
        $this->assertSame('4.000', $items[0]->outstandingQuantity());
        $this->assertSame('1.125', $items[1]->outstandingQuantity());
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->fresh()->status);

        $second = $this->receive($actor, $purchaseOrder, [
            $this->line($items[0], '4', '11'),
            $this->line($items[1], '1.125', '22'),
        ]);
        $this->assertCount(2, $second->items);
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->fresh()->status);
        $this->assertSame(['4.000', '1.250'], [$whole->fresh()->current_stock, $fractional->fresh()->current_stock]);
    }

    public function test_transfer_removes_source_receiving_quantity_and_final_other_line_receipt_closes_with_remainder(): void
    {
        $actor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $firstVariant = $this->variant($product, ['size' => 'First']);
        $secondVariant = $this->variant($product, ['size' => 'Second']);
        $this->initialize($firstVariant, $actor);
        $this->initialize($secondVariant, $actor);
        [$source, $items] = $this->purchaseOrder($actor, [
            [$firstVariant, '3.000', '10.00'],
            [$secondVariant, '2.000', '11.00'],
        ]);
        $child = app(CreateFollowUpPurchaseOrder::class)->execute(
            $actor,
            $source,
            Str::uuid()->toString(),
            'Replacement Supplier',
            null,
            [['source_purchase_order_item_id' => $items[0]->id, 'expected_unit_cost' => '12.00']],
        );
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $source->fresh()->status);
        $this->assertSame('3.000', PurchaseOrderItemTransfer::query()->sole()->quantity);
        $this->assertSame('0.000', $items[0]->outstandingQuantity());

        $this->assertValidation(fn () => $this->receive($actor, $source, [$this->line($items[0], '0.001', '15.00')]), 'items.0.accepted_quantity');
        $this->assertValidation(fn () => $this->receive($actor, $source, [$this->line($items[1], '2.001', '15.00')]), 'items.0.accepted_quantity');
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame('0.000', $firstVariant->fresh()->current_stock);

        $token = Str::uuid()->toString();
        $line = [$this->line($items[1], '2.000', '15.00')];
        $receipt = $this->receive($actor, $source, $line, $token);
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $source->fresh()->status);
        $replay = $this->receive($actor, $source, $line, strtoupper($token));
        $this->assertSame($receipt->id, $replay->id);
        $this->assertSame(1, Restock::query()->count());

        $childLine = $child->items()->sole();
        $this->receive($actor, $child, [$this->line($childLine, '3.000', '16.00')]);
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $child->fresh()->status);
        $this->assertSame('3.000', $firstVariant->fresh()->current_stock);
        $this->assertSame('2.000', $secondVariant->fresh()->current_stock);
    }

    public function test_prior_accepted_plus_transferred_quantity_limits_source_receiving(): void
    {
        $actor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $firstVariant = $this->variant($product, ['size' => 'First']);
        $secondVariant = $this->variant($product, ['size' => 'Second']);
        $this->initialize($firstVariant, $actor);
        $this->initialize($secondVariant, $actor);
        [$source, $items] = $this->purchaseOrder($actor, [
            [$firstVariant, '3.000', '10.00'],
            [$secondVariant, '1.000', '11.00'],
        ]);
        $this->receive($actor, $source, [$this->line($items[0], '1.000', '12.00')]);
        $child = app(CreateFollowUpPurchaseOrder::class)->execute(
            $actor,
            $source,
            Str::uuid()->toString(),
            'Replacement Supplier',
            null,
            [['source_purchase_order_item_id' => $items[0]->id, 'expected_unit_cost' => '12.00']],
        );
        $this->assertSame('1.000', $items[0]->acceptedQuantity());
        $this->assertSame('2.000', PurchaseOrderItemTransfer::query()->sole()->quantity);
        $this->assertSame('0.000', $items[0]->outstandingQuantity());
        $this->assertValidation(fn () => $this->receive($actor, $source, [$this->line($items[0], '0.001', '13.00')]), 'items.0.accepted_quantity');
        $this->assertSame(1, Restock::query()->count());

        $this->receive($actor, $source, [$this->line($items[1], '1.000', '13.00')]);
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $source->fresh()->status);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $child->fresh()->status);
    }

    public function test_follow_up_child_with_its_own_outgoing_transfer_closes_after_other_line_is_received(): void
    {
        $actor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $firstVariant = $this->variant($product, ['size' => 'First']);
        $secondVariant = $this->variant($product, ['size' => 'Second']);
        $this->initialize($firstVariant, $actor);
        $this->initialize($secondVariant, $actor);
        [$source, $items] = $this->purchaseOrder($actor, [
            [$firstVariant, '2.000', '10.00'],
            [$secondVariant, '1.000', '11.00'],
        ]);
        $service = app(CreateFollowUpPurchaseOrder::class);
        $child = $service->execute($actor, $source, Str::uuid()->toString(), 'Supplier', null, [
            ['source_purchase_order_item_id' => $items[0]->id, 'expected_unit_cost' => '12.00'],
            ['source_purchase_order_item_id' => $items[1]->id, 'expected_unit_cost' => '13.00'],
        ]);
        $childItems = $child->items()->orderBy('id')->get();
        $service->execute($actor, $child, Str::uuid()->toString(), 'Next Supplier', null, [
            ['source_purchase_order_item_id' => $childItems[0]->id, 'expected_unit_cost' => '14.00'],
        ]);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $child->fresh()->status);

        $this->receive($actor, $child, [$this->line($childItems[1], '1.000', '15.00')]);
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $child->fresh()->status);
    }

    public function test_invalid_receipt_shapes_and_domain_values_are_rejected_without_mutation(): void
    {
        $actor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $variant = $this->variant($product);
        $foreignVariant = $this->variant($product, ['size' => 'Foreign']);
        $this->initialize($variant, $actor);
        $this->initialize($foreignVariant, $actor);
        [$purchaseOrder, $items] = $this->purchaseOrder($actor, [[$variant, '2.000', '10.00']]);
        [$foreignOrder, $foreignItems] = $this->purchaseOrder($actor, [[$foreignVariant, '1.000', '10.00']]);

        $cases = [
            [[], 'items'],
            [[$this->line($items[0], '0', '10')], 'items.0.accepted_quantity'],
            [[$this->line($items[0], '1', '10'), $this->line($items[0], '1', '10')], 'items.1.purchase_order_item_id'],
            [[$this->line($foreignItems[0], '1', '10')], 'items.0.purchase_order_item_id'],
            [[$this->line($items[0], '2.001', '10')], 'items.0.accepted_quantity'],
            [[$this->line($items[0], '0.500', '10')], 'items.0.quantity'],
            [[$this->line($items[0], '1', '-0.01')], 'items.0.actual_unit_cost'],
        ];
        foreach ($cases as $case => [$lines, $key]) {
            $this->assertValidation(
                fn () => $this->receive($actor, $purchaseOrder, $lines),
                $key,
                "Validation case {$case} should fail.",
            );
            $this->assertNoReceiptMutation($purchaseOrder, [$variant, $foreignVariant]);
        }

        $foreignOrder->status = PurchaseOrder::STATUS_COMPLETED;
        $foreignOrder->save();
        $this->assertValidation(
            fn () => $this->receive($actor, $foreignOrder, [$this->line($foreignItems[0], '1', '10')]),
            'purchase_order',
        );
        $this->assertNoReceiptMutation($purchaseOrder, [$variant, $foreignVariant]);
    }

    public function test_inactive_hierarchy_and_missing_initial_stock_are_rejected_without_mutation(): void
    {
        $actor = User::factory()->create();
        foreach (['category', 'product', 'variant', 'uninitialized'] as $case) {
            $category = $this->category();
            $product = $this->product($category);
            $variant = $this->variant($product, ['size' => $case]);
            if ($case !== 'uninitialized') {
                $this->initialize($variant, $actor);
            }
            if ($case === 'category') {
                $category->status = Category::STATUS_ARCHIVED;
                $category->save();
            } elseif ($case === 'product') {
                $product->status = Product::STATUS_ARCHIVED;
                $product->save();
            } elseif ($case === 'variant') {
                $variant->status = ProductVariant::STATUS_ARCHIVED;
                $variant->save();
            }
            [$purchaseOrder, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);

            $this->assertValidation(
                fn () => $this->receive($actor, $purchaseOrder, [$this->line($items[0], '1', '10')]),
                'items.0.purchase_order_item_id',
            );
            $this->assertSame('0.000', $variant->fresh()->current_stock);
            $this->assertSame(PurchaseOrder::STATUS_PENDING, $purchaseOrder->fresh()->status);
        }
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_active_admin_and_staff_are_allowed_while_inactive_unsupported_and_missing_actors_are_rejected(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $actor) {
            $variant = $this->variant($this->product($this->category()), ['size' => 'Allowed '.$actor->id]);
            $this->initialize($variant, $actor);
            [$purchaseOrder, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);
            $receipt = $this->receive($actor, $purchaseOrder, [$this->line($items[0], '1', '10')]);
            $this->assertSame($actor->id, $receipt->recorded_by);
        }

        $unsupported = User::factory()->create();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('users')->where('id', $unsupported->id)->update(['role' => 'owner']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');

        foreach ([
            User::factory()->disabled()->create(),
            $unsupported,
            User::factory()->make(),
        ] as $actor) {
            $purchaseOrder = PurchaseOrder::query()->firstOrFail();
            $item = $purchaseOrder->items()->firstOrFail();
            $this->assertValidation(
                fn () => $this->receive($actor, $purchaseOrder, [$this->line($item, '1', '10')]),
                'actor',
            );
        }
    }

    public function test_conflicting_token_reuse_is_rejected_without_additional_mutation(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        [$purchaseOrder, $items] = $this->purchaseOrder($actor, [[$variant, '3.000', '10.00']]);
        $token = Str::uuid()->toString();
        $first = $this->receive($actor, $purchaseOrder, [$this->line($items[0], '1', '10')], $token);
        $before = [$variant->fresh()->current_stock, $purchaseOrder->fresh()->status, RestockItem::query()->count()];

        $this->assertValidation(
            fn () => $this->receive($actor, $purchaseOrder, [$this->line($items[0], '2', '10')], $token),
            'submission_token',
        );
        $this->assertSame($before, [$variant->fresh()->current_stock, $purchaseOrder->fresh()->status, RestockItem::query()->count()]);
        $this->assertSame($first->id, Restock::query()->sole()->id);
    }

    public function test_forced_movement_failure_rolls_back_header_items_stock_cost_and_status(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '2.000', 'cost_price' => '30.00']);
        $this->initialize($variant, $actor);
        [$purchaseOrder, $items] = $this->purchaseOrder($actor, [[$variant, '2.000', '25.00']]);
        DB::unprepared("CREATE TRIGGER fail_po_restock_movement BEFORE INSERT ON stock_movements WHEN NEW.movement_type = 'RESTOCK' BEGIN SELECT RAISE(ABORT, 'forced PO movement failure'); END");

        try {
            $this->receive($actor, $purchaseOrder, [$this->line($items[0], '1', '35')]);
            $this->fail('The RESTOCK movement should have failed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced PO movement failure', $exception->getMessage());
        }

        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame(['2.000', '30.00'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $purchaseOrder->fresh()->status);
    }

    /** @param list<array{0: ProductVariant, 1: string, 2: string}> $definitions
     * @return array{PurchaseOrder, list<PurchaseOrderItem>}
     */
    private function purchaseOrder(User $creator, array $definitions): array
    {
        $purchaseOrder = new PurchaseOrder(['supplier_name' => 'Supplier', 'notes' => null]);
        $purchaseOrder->submission_token = Str::uuid()->toString();
        $purchaseOrder->created_by = $creator->id;
        $purchaseOrder->status = PurchaseOrder::STATUS_PENDING;
        $purchaseOrder->save();

        $items = [];
        foreach ($definitions as [$variant, $quantity, $cost]) {
            $variant->loadMissing('product:id,name');
            $items[] = PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_variant_id' => $variant->id,
                'product_name_snapshot' => $variant->product->name,
                'size_snapshot' => $variant->size,
                'type_series_snapshot' => $variant->type_series,
                'thickness_snapshot' => $variant->thickness,
                'unit_snapshot' => $variant->unit,
                'ordered_quantity' => $quantity,
                'expected_unit_cost' => $cost,
            ]);
        }

        return [$purchaseOrder, $items];
    }

    /** @return array<string, mixed> */
    private function line(PurchaseOrderItem $item, string $quantity, string $cost): array
    {
        return [
            'purchase_order_item_id' => $item->id,
            'accepted_quantity' => $quantity,
            'actual_unit_cost' => $cost,
        ];
    }

    /** @param list<array<string, mixed>> $items */
    private function receive(
        User $actor,
        PurchaseOrder $purchaseOrder,
        array $items,
        ?string $token = null,
        mixed $reference = null,
        mixed $notes = null,
    ): Restock {
        return $this->service->execute(
            $actor,
            $purchaseOrder,
            $token ?? Str::uuid()->toString(),
            $reference,
            $notes,
            $items,
        );
    }

    private function assertValidation(callable $operation, string $key, string $message = ''): void
    {
        try {
            $operation();
            $this->fail($message !== '' ? $message : "Expected validation error for {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors(), $message);
        }
    }

    /** @param list<ProductVariant> $variants */
    private function assertNoReceiptMutation(PurchaseOrder $purchaseOrder, array $variants): void
    {
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $purchaseOrder->fresh()->status);
        foreach ($variants as $variant) {
            $this->assertSame('0.000', $variant->fresh()->current_stock);
        }
    }
}

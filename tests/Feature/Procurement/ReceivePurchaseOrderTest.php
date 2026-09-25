<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemTransfer;
use App\Models\Restock;
use App\Models\RestockDamageItem;
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

    public function test_damage_only_receipt_preserves_snapshot_stock_cost_outstanding_and_pending_status(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), [
            'unit' => 'kg', 'quantity_mode' => 'fractional', 'current_stock' => '1.250', 'cost_price' => '30.00',
        ]);
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '2.000', '25.00']]);
        $line = $items[0];
        $originalName = $line->product_name_snapshot;
        $originalSize = $line->size_snapshot;
        $variant->product->name = 'Changed catalog name';
        $variant->product->save();
        $variant->size = 'Changed catalog size';
        $variant->save();
        $beforeMovements = StockMovement::query()->count();

        $receipt = $this->receive($actor, $order, [$this->damageLine($line, '3.125', "  Bent  on\n arrival  ")]);
        $damage = $receipt->damageItems->sole();

        $this->assertSame('0.00', $receipt->total_cost);
        $this->assertCount(0, $receipt->items);
        $this->assertSame('3.125', $damage->damaged_quantity);
        $this->assertSame('Bent on arrival', $damage->damage_note);
        $this->assertSame($line->id, $damage->purchase_order_item_id);
        $this->assertSame($variant->id, $damage->product_variant_id);
        $this->assertSame($originalName, $damage->product_name_snapshot);
        $this->assertSame($originalSize, $damage->size_snapshot);
        $this->assertSame($line->type_series_snapshot, $damage->type_series_snapshot);
        $this->assertSame($line->thickness_snapshot, $damage->thickness_snapshot);
        $this->assertSame($line->unit_snapshot, $damage->unit_snapshot);
        $this->assertTrue($damage->restock->recordedBy->is($actor));
        $this->assertNotNull($damage->created_at);
        $this->assertSame(['1.250', '30.00'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame('0.000', $line->acceptedQuantity());
        $this->assertSame('0.000', $line->transferredQuantity());
        $this->assertSame('2.000', $line->outstandingQuantity());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_mixed_receipt_posts_only_accepted_quantity_and_allows_damage_above_outstanding(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), [
            'unit' => 'kg', 'quantity_mode' => 'fractional', 'current_stock' => '1.000', 'cost_price' => '30.00',
        ]);
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '5.000', '25.00']]);
        $line = array_merge($this->line($items[0], '2.000', '40.00'), [
            'damaged_quantity' => '7.000', 'damage_note' => 'Cracked in transit',
        ]);

        $receipt = $this->receive($actor, $order, [$line]);

        $this->assertSame('80.00', $receipt->total_cost);
        $this->assertSame('2.000', $receipt->items->sole()->quantity);
        $this->assertSame('7.000', $receipt->damageItems->sole()->damaged_quantity);
        $this->assertSame(['3.000', '40.00'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame('2.000', $items[0]->acceptedQuantity());
        $this->assertSame('3.000', $items[0]->outstandingQuantity());
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->fresh()->status);

        $this->assertValidation(fn () => $this->receive($actor, $order, [array_merge(
            $this->line($items[0], '3.001', '40.00'),
            ['damaged_quantity' => '1.000', 'damage_note' => 'Another defect'],
        )]), 'items.0.accepted_quantity');
        $this->assertSame(1, RestockDamageItem::query()->count());
    }

    public function test_mixed_receipt_supports_accepted_only_damage_only_and_both_on_separate_lines(): void
    {
        $actor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $variants = [];
        for ($i = 0; $i < 3; $i++) {
            $variants[] = $this->variant($product, ['size' => 'Line '.$i]);
            $this->initialize($variants[$i], $actor);
        }
        [$order, $items] = $this->purchaseOrder($actor, [
            [$variants[0], '2.000', '10.00'],
            [$variants[1], '2.000', '10.00'],
            [$variants[2], '2.000', '10.00'],
        ]);

        $receipt = $this->receive($actor, $order, [
            $this->line($items[0], '1', '11'),
            $this->damageLine($items[1], '1', 'Bent'),
            array_merge($this->line($items[2], '1', '12'), ['damaged_quantity' => '2', 'damage_note' => 'Broken']),
        ]);

        $this->assertSame('23.00', $receipt->total_cost);
        $this->assertCount(2, $receipt->items);
        $this->assertCount(2, $receipt->damageItems);
        $this->assertSame(['1.000', '0.000', '1.000'], array_map(fn (ProductVariant $variant): string => $variant->fresh()->current_stock, $variants));
        $this->assertSame(['1.000', '2.000', '1.000'], array_map(fn (PurchaseOrderItem $item): string => $item->outstandingQuantity(), $items));
    }

    public function test_multiple_damage_receipts_have_no_cumulative_order_cap_and_keep_pending_status(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);

        $first = $this->receive($actor, $order, [$this->damageLine($items[0], '2', 'First shipment')]);
        $second = $this->receive($actor, $order, [$this->damageLine($items[0], '3', 'Replacement shipment')]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Restock::query()->count());
        $this->assertSame(2, RestockDamageItem::query()->count());
        $this->assertSame('1.000', $items[0]->outstandingQuantity());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_damage_only_after_partial_acceptance_preserves_partially_received_status(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '3.000', '10.00']]);
        $this->receive($actor, $order, [$this->line($items[0], '1', '12')]);

        $receipt = $this->receive($actor, $order, [$this->damageLine($items[0], '4', 'Damaged replacement')]);

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->fresh()->status);
        $this->assertSame('2.000', $items[0]->outstandingQuantity());
        $this->assertSame('1.000', $variant->fresh()->current_stock);
        $this->assertSame('12.00', $variant->fresh()->cost_price);
        $this->assertCount(0, $receipt->items);
        $this->assertCount(1, $receipt->damageItems);
    }

    public function test_damage_quantity_and_note_validation_rejects_invalid_shapes_without_writes(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '2.000', '10.00']]);
        $base = $this->damageLine($items[0], '1', 'Damaged');

        foreach (['0', '-1', '1.001', '1.1234', '100000000000.000', [], 1] as $quantity) {
            $this->assertValidation(fn () => $this->receive($actor, $order, [array_replace($base, ['damaged_quantity' => $quantity])]), 'items.0.damaged_quantity');
        }
        foreach ([null, '', '   ', [], str_repeat('a', 1001)] as $note) {
            $this->assertValidation(fn () => $this->receive($actor, $order, [array_replace($base, ['damage_note' => $note])]), 'items.0.damage_note');
        }
        $this->assertValidation(fn () => $this->receive($actor, $order, [[
            'purchase_order_item_id' => $items[0]->id, 'damage_note' => 'Orphan note',
        ]]), 'items.0.damaged_quantity');
        $this->assertValidation(fn () => $this->receive($actor, $order, [[
            'purchase_order_item_id' => $items[0]->id,
        ]]), 'items.0');
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockDamageItem::query()->count());
        $this->assertSame('0.000', $variant->fresh()->current_stock);
    }

    public function test_fractional_damage_uses_exact_three_decimal_quantity(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['unit' => 'kg', 'quantity_mode' => 'fractional']);
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);

        $receipt = $this->receive($actor, $order, [$this->damageLine($items[0], '0.125', 'Measured damage')]);

        $this->assertSame('0.125', $receipt->damageItems->sole()->damaged_quantity);
        $this->assertSame('1.000', $items[0]->outstandingQuantity());
    }

    public function test_damage_receiving_requires_active_initialized_open_lines_and_valid_actor(): void
    {
        $actor = User::factory()->admin()->create();
        foreach (['category', 'product', 'variant', 'uninitialized', 'completed', 'closed_with_remainder'] as $case) {
            $category = $this->category();
            $product = $this->product($category);
            $variant = $this->variant($product);
            if ($case !== 'uninitialized') {
                $this->initialize($variant, $actor);
            }
            [$order, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);
            if ($case === 'category') {
                $category->status = Category::STATUS_ARCHIVED;
                $category->save();
            } elseif ($case === 'product') {
                $product->status = Product::STATUS_ARCHIVED;
                $product->save();
            } elseif ($case === 'variant') {
                $variant->status = ProductVariant::STATUS_ARCHIVED;
                $variant->save();
            } elseif ($case === 'completed' || $case === 'closed_with_remainder') {
                $order->status = $case;
                $order->save();
            }

            $key = in_array($case, ['completed', 'closed_with_remainder'], true) ? 'purchase_order' : 'items.0.purchase_order_item_id';
            $this->assertValidation(fn () => $this->receive($actor, $order, [$this->damageLine($items[0], '1', 'Defect')]), $key);
        }
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockDamageItem::query()->count());

        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);
        $this->assertValidation(fn () => $this->receive(User::factory()->disabled()->create(), $order, [
            $this->damageLine($items[0], '1', 'Defect'),
        ]), 'actor');
    }

    public function test_damage_rejects_a_foreign_or_fully_satisfied_line_on_an_open_order(): void
    {
        $actor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $first = $this->variant($product, ['size' => 'First']);
        $second = $this->variant($product, ['size' => 'Second']);
        $this->initialize($first, $actor);
        $this->initialize($second, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$first, '1.000', '10.00'], [$second, '1.000', '10.00']]);
        [$foreignOrder, $foreignItems] = $this->purchaseOrder($actor, [[$first, '1.000', '10.00']]);

        $this->assertValidation(fn () => $this->receive($actor, $order, [
            $this->damageLine($foreignItems[0], '1', 'Foreign line'),
        ]), 'items.0.purchase_order_item_id');
        $this->receive($actor, $order, [$this->line($items[0], '1', '11')]);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->fresh()->status);
        $this->assertSame('0.000', $items[0]->outstandingQuantity());

        $this->assertValidation(fn () => $this->receive($actor, $order, [
            $this->damageLine($items[0], '1', 'Already satisfied'),
        ]), 'items.0.damaged_quantity');
        $this->assertSame(0, RestockDamageItem::query()->count());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $foreignOrder->fresh()->status);
    }

    public function test_damage_only_replay_after_completion_and_semantic_conflicts(): void
    {
        $actor = User::factory()->admin()->create();
        $otherActor = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $variant = $this->variant($product);
        $otherVariant = $this->variant($product, ['size' => 'Other']);
        $this->initialize($variant, $actor);
        $this->initialize($otherVariant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00'], [$otherVariant, '1.000', '10.00']]);
        [$otherOrder] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);
        $token = Str::uuid()->toString();
        $line = [$this->damageLine($items[0], '2', '  Bent  item  ')];
        $first = $this->receive($actor, $order, $line, $token);
        $this->receive($actor, $order, [
            $this->line($items[0], '1', '11'), $this->line($items[1], '1', '12'),
        ]);
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->fresh()->status);

        $replay = $this->receive($actor, $order, [$this->damageLine($items[0], '2.000', 'Bent item')], strtoupper($token));
        $this->assertSame($first->id, $replay->id);
        $this->assertCount(0, $replay->items);
        $this->assertCount(1, $replay->damageItems);

        foreach ([
            [$actor, $order, [$this->damageLine($items[0], '3', 'Bent item')]],
            [$actor, $order, [$this->damageLine($items[0], '2', 'Different note')]],
            [$actor, $order, [$this->line($items[0], '1', '11')]],
            [$actor, $order, [$this->damageLine($items[1], '2', 'Bent item')]],
            [$otherActor, $order, $line],
            [$actor, $otherOrder, $line],
        ] as [$requestActor, $requestOrder, $requestLines]) {
            $this->assertValidation(fn () => $this->receive($requestActor, $requestOrder, $requestLines, $token), 'submission_token');
        }
        $this->assertSame(2, Restock::query()->count());
        $this->assertSame(1, RestockDamageItem::query()->count());
    }

    public function test_mixed_replay_compares_accepted_and_damage_sets_after_stock_cost_and_status_change(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['cost_price' => '20.00']);
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '1.000', '10.00']]);
        $token = Str::uuid()->toString();
        $line = [array_merge($this->line($items[0], '1', '12'), ['damaged_quantity' => '4', 'damage_note' => 'Broken'])];

        $first = $this->receive($actor, $order, $line, $token);
        $replay = $this->receive($actor, $order, [array_merge($this->line($items[0], '1.000', '12.00'), [
            'damaged_quantity' => '4.000', 'damage_note' => 'Broken',
        ])], strtoupper($token));

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(['1.000', '12.00'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(1, RestockItem::query()->count());
        $this->assertSame(1, RestockDamageItem::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertValidation(fn () => $this->receive($actor, $order, [$this->line($items[0], '1', '12')], $token), 'submission_token');
        $this->assertValidation(fn () => $this->receive($actor, $order, [array_merge($this->line($items[0], '1', '13'), [
            'damaged_quantity' => '4', 'damage_note' => 'Broken',
        ])], $token), 'submission_token');
    }

    public function test_damage_does_not_reduce_follow_up_transfer_quantity(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '5.000', '10.00']]);
        $this->receive($actor, $order, [$this->damageLine($items[0], '7', 'Damaged shipment')]);

        $child = app(CreateFollowUpPurchaseOrder::class)->execute(
            $actor, $order, Str::uuid()->toString(), 'Replacement supplier', null,
            [['source_purchase_order_item_id' => $items[0]->id, 'expected_unit_cost' => '11.00']],
        );

        $this->assertSame('5.000', PurchaseOrderItemTransfer::query()->sole()->quantity);
        $this->assertSame('0.000', $items[0]->outstandingQuantity());
        $this->assertSame('5.000', $child->items()->sole()->outstandingQuantity());
        $this->assertSame(1, RestockDamageItem::query()->count());
        $this->assertValidation(fn () => $this->receive($actor, $order, [$this->damageLine($items[0], '1', 'Late defect')]), 'purchase_order');
    }

    public function test_failed_damage_insert_rolls_back_mixed_receipt_completely(): void
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '2.000', 'cost_price' => '30.00']);
        $this->initialize($variant, $actor);
        [$order, $items] = $this->purchaseOrder($actor, [[$variant, '2.000', '25.00']]);
        $beforeMovements = StockMovement::query()->count();
        DB::unprepared("CREATE TRIGGER fail_po_damage BEFORE INSERT ON restock_damage_items BEGIN SELECT RAISE(ABORT, 'forced damage failure'); END");

        try {
            $this->receive($actor, $order, [array_merge($this->line($items[0], '1', '35'), [
                'damaged_quantity' => '2', 'damage_note' => 'Broken',
            ])]);
            $this->fail('The damage insert should have failed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced damage failure', $exception->getMessage());
        }

        $this->assertSame(0, Restock::query()->count());
        $this->assertSame(0, RestockItem::query()->count());
        $this->assertSame(0, RestockDamageItem::query()->count());
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertSame(['2.000', '30.00'], [$variant->fresh()->current_stock, $variant->fresh()->cost_price]);
        $this->assertSame('2.000', $items[0]->outstandingQuantity());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $order->fresh()->status);
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

    /** @return array<string, mixed> */
    private function damageLine(PurchaseOrderItem $item, string $quantity, string $note): array
    {
        return [
            'purchase_order_item_id' => $item->id,
            'damaged_quantity' => $quantity,
            'damage_note' => $note,
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

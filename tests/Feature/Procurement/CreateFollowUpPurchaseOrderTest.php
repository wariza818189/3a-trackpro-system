<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemTransfer;
use App\Models\User;
use App\Queries\Procurement\PurchaseOrderCoverageQuery;
use App\Services\Procurement\CreateFollowUpPurchaseOrder;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateFollowUpPurchaseOrderTest extends PurchaseOrderCreationTestCase
{
    private CreateFollowUpPurchaseOrder $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CreateFollowUpPurchaseOrder::class);
    }

    public function test_admin_transfers_exact_remainder_with_source_snapshots_cost_lineage_and_no_inventory_mutation(): void
    {
        [$source, $items, $variants] = $this->source(['1.250']);
        $sourceItem = $items[0];
        DB::table('restock_items')->insert(['purchase_order_item_id' => $sourceItem->id, 'quantity' => '0.375']);
        $product = $variants[0]->product;
        $product->name = 'New catalog name';
        $product->save();
        $variants[0]->size = 'New catalog size';
        $variants[0]->save();
        $beforeStock = $variants[0]->fresh()->current_stock;
        $beforeMovementCount = DB::table('stock_movements')->count();
        $beforeCoverage = $this->coverage($variants[0]);

        $child = $this->followUp($this->admin, $source, [$this->selection($sourceItem, '31.25')], supplier: '  New  Supplier  ', notes: '  Later  shipment  ');
        $target = $child->items->sole();
        $transfer = PurchaseOrderItemTransfer::query()->sole();

        $this->assertSame($source->id, $child->parent_purchase_order_id);
        $this->assertSame($this->admin->id, $child->created_by);
        $this->assertSame('New Supplier', $child->supplier_name);
        $this->assertSame('Later shipment', $child->notes);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $child->status);
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $source->fresh()->status);
        $this->assertSame('0.875', $target->ordered_quantity);
        $this->assertSame('31.25', $target->expected_unit_cost);
        $this->assertSame($sourceItem->product_variant_id, $target->product_variant_id);
        foreach (['product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot'] as $field) {
            $this->assertSame($sourceItem->{$field}, $target->{$field});
        }
        $this->assertNotSame($product->name, $target->product_name_snapshot);
        $this->assertNotSame($variants[0]->size, $target->size_snapshot);
        $this->assertSame($sourceItem->id, $transfer->source_purchase_order_item_id);
        $this->assertSame($target->id, $transfer->target_purchase_order_item_id);
        $this->assertSame('0.875', $transfer->quantity);
        $this->assertSame($this->admin->id, $transfer->created_by);
        $this->assertSame('0.000', $sourceItem->outstandingQuantity());
        $this->assertSame('0.875', $target->outstandingQuantity());
        $this->assertSame($beforeCoverage, $this->coverage($variants[0]));
        $this->assertSame($beforeStock, $variants[0]->fresh()->current_stock);
        $this->assertSame($beforeMovementCount, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('restocks')->count());
        $this->assertSame(1, DB::table('restock_items')->count());
    }

    public function test_partial_selection_then_second_follow_up_closes_source_and_preserves_unselected_demand(): void
    {
        [$source, $items] = $this->source(['2.000', '3.000']);
        $first = $this->followUp($this->admin, $source, [$this->selection($items[0])]);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $source->fresh()->status);
        $this->assertSame('0.000', $items[0]->outstandingQuantity());
        $this->assertSame('3.000', $items[1]->outstandingQuantity());
        $this->assertSame('2.000', $first->items->sole()->ordered_quantity);

        $second = $this->followUp($this->admin, $source, [$this->selection($items[1])]);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($source->id, $second->parent_purchase_order_id);
        $this->assertSame('3.000', $second->items->sole()->ordered_quantity);
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $source->fresh()->status);
        $this->assertSame(2, PurchaseOrderItemTransfer::query()->count());
    }

    public function test_child_may_source_another_follow_up_and_incoming_transfer_does_not_close_it(): void
    {
        [$source, $items] = $this->source(['2.000']);
        $child = $this->followUp($this->admin, $source, [$this->selection($items[0])]);
        $grandchild = $this->followUp($this->admin, $child, [$this->selection($child->items->sole())]);

        $this->assertSame($child->id, $grandchild->parent_purchase_order_id);
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $child->fresh()->status);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $grandchild->status);
        $this->assertSame('2.000', $grandchild->items->sole()->ordered_quantity);
    }

    public function test_invalid_source_lines_and_terminal_or_empty_sources_are_rejected_without_writes(): void
    {
        [$source, $items] = $this->source(['2.000', '1.000']);
        [$other, $otherItems] = $this->source(['1.000']);
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, []), 'items');
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [
            $this->selection($items[0]), $this->selection($items[0]),
        ]), 'items.1.source_purchase_order_item_id');
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($otherItems[0])]), 'items.0.source_purchase_order_item_id');

        DB::table('restock_items')->insert(['purchase_order_item_id' => $items[0]->id, 'quantity' => '2.000']);
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[0])]), 'items.0.source_purchase_order_item_id');
        $source->status = PurchaseOrder::STATUS_COMPLETED;
        $source->save();
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[1])]), 'purchase_order');
        $source->status = PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER;
        $source->save();
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[1])]), 'purchase_order');

        $this->assertSame(2, PurchaseOrder::query()->count());
        $this->assertSame(0, PurchaseOrderItemTransfer::query()->count());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $other->fresh()->status);
    }

    public function test_already_transferred_source_line_cannot_transfer_twice(): void
    {
        [$source, $items] = $this->source(['2.000', '1.000']);
        $this->followUp($this->admin, $source, [$this->selection($items[0])]);
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[0])]), 'items.0.source_purchase_order_item_id');
        $this->assertSame(1, PurchaseOrderItemTransfer::query()->count());
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $source->fresh()->status);
    }

    public function test_inactive_hierarchy_and_absent_initial_stock_still_allow_follow_up(): void
    {
        [$source, $items, $variants] = $this->source(['2.000']);
        $variants[0]->status = ProductVariant::STATUS_ARCHIVED;
        $variants[0]->save();
        $product = $variants[0]->product;
        $product->status = Product::STATUS_ARCHIVED;
        $product->save();
        $category = $product->category;
        $category->status = Category::STATUS_ARCHIVED;
        $category->save();
        $this->assertSame(0, DB::table('stock_movements')->count());

        $child = $this->followUp($this->admin, $source, [$this->selection($items[0])]);
        $this->assertSame('2.000', $child->items->sole()->ordered_quantity);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $child->status);
        $this->assertSame(ProductVariant::STATUS_ARCHIVED, $variants[0]->fresh()->status);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_only_persisted_active_admin_can_create_follow_up(): void
    {
        [$source, $items] = $this->source(['1.000']);
        $unsupported = User::factory()->create();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('users')->where('id', $unsupported->id)->update(['role' => 'owner']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        foreach ([User::factory()->create(), User::factory()->admin()->disabled()->create(), $unsupported->fresh(), User::factory()->admin()->make()] as $actor) {
            $this->assertServiceValidation(fn () => $this->followUp($actor, $source, [$this->selection($items[0])]), 'actor');
        }
        $this->assertSame(1, PurchaseOrder::query()->count());
    }

    public function test_supplier_cost_and_shape_validation_follow_purchase_order_conventions(): void
    {
        [$source, $items] = $this->source(['1.000']);
        foreach ([null, '', '   ', str_repeat('S', 151)] as $supplier) {
            $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[0])], supplier: $supplier), 'supplier_name');
        }
        foreach (['-1', '1.001', '1e3', '10000000000.00'] as $cost) {
            $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[0], $cost)]), 'items.0.expected_unit_cost');
        }
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [[
            'source_purchase_order_item_id' => $items[0]->id,
            'expected_unit_cost' => '2.00',
            'ordered_quantity' => '1.000',
        ]]), 'items.0');

        $child = $this->followUp($this->admin, $source, [$this->selection($items[0], '020.00')], supplier: "  Follow\u{2003}up  Supplier ", notes: "  New\n delivery  ");
        $this->assertSame('Follow up Supplier', $child->supplier_name);
        $this->assertSame('New delivery', $child->notes);
        $this->assertSame('20.00', $child->items->sole()->expected_unit_cost);
    }

    public function test_equivalent_token_replay_returns_same_child_after_source_closes(): void
    {
        [$source, $items] = $this->source(['1.250']);
        $token = Str::uuid()->toString();
        $first = $this->followUp($this->admin, $source, [$this->selection($items[0], '0')], $token, 'Supplier', 'Same note');
        $replay = $this->followUp($this->admin, $source, [$this->selection($items[0], '00.00')], strtoupper($token), ' Supplier ', ' Same  note ');

        $this->assertSame($first->id, $replay->id);
        $this->assertFalse($replay->wasRecentlyCreated);
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $source->fresh()->status);
        $this->assertSame(2, PurchaseOrder::query()->count());
        $this->assertSame(2, PurchaseOrderItem::query()->count());
        $this->assertSame(1, PurchaseOrderItemTransfer::query()->count());
    }

    public function test_token_conflicts_including_ordinary_po_are_controlled_and_mutation_free(): void
    {
        [$source, $items] = $this->source(['2.000', '1.000']);
        [$otherSource, $otherItems] = $this->source(['1.000']);
        $token = Str::uuid()->toString();
        $this->followUp($this->admin, $source, [$this->selection($items[0])], $token);
        $before = [PurchaseOrder::query()->count(), PurchaseOrderItem::query()->count(), PurchaseOrderItemTransfer::query()->count(), $source->fresh()->status];
        foreach ([
            [$this->admin, $source, [$this->selection($items[0])], 'Different Supplier', null],
            [$this->admin, $source, [$this->selection($items[0])], 'Supplier', 'Different note'],
            [$this->admin, $source, [$this->selection($items[1])], 'Supplier', null],
            [$this->admin, $source, [$this->selection($items[0], '21.00')], 'Supplier', null],
            [$this->admin, $otherSource, [$this->selection($otherItems[0])], 'Supplier', null],
            [User::factory()->admin()->create(), $source, [$this->selection($items[0])], 'Supplier', null],
        ] as [$actor, $order, $selection, $supplier, $notes]) {
            $this->assertServiceValidation(fn () => $this->followUp($actor, $order, $selection, $token, $supplier, $notes), 'submission_token');
        }
        $this->assertSame($before, [PurchaseOrder::query()->count(), PurchaseOrderItem::query()->count(), PurchaseOrderItemTransfer::query()->count(), $source->fresh()->status]);

        $ordinaryToken = $otherSource->submission_token;
        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[1])], $ordinaryToken), 'submission_token');
        $this->assertSame($before, [PurchaseOrder::query()->count(), PurchaseOrderItem::query()->count(), PurchaseOrderItemTransfer::query()->count(), $source->fresh()->status]);
    }

    public function test_second_transfer_failure_rolls_back_child_lines_evidence_and_status(): void
    {
        [$source, $items] = $this->source(['2.000', '3.000']);
        DB::unprepared("CREATE TRIGGER fail_second_transfer BEFORE INSERT ON purchase_order_item_transfers WHEN (SELECT COUNT(*) FROM purchase_order_item_transfers) = 1 BEGIN SELECT RAISE(ABORT, 'forced transfer failure'); END");

        try {
            $this->followUp($this->admin, $source, [$this->selection($items[0]), $this->selection($items[1])]);
            $this->fail('The second transfer insert should fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced transfer failure', $exception->getMessage());
        }
        $this->assertSame(1, PurchaseOrder::query()->count());
        $this->assertSame(2, PurchaseOrderItem::query()->count());
        $this->assertSame(0, PurchaseOrderItemTransfer::query()->count());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $source->fresh()->status);
        $this->assertSame(['2.000', '3.000'], [$items[0]->outstandingQuantity(), $items[1]->outstandingQuantity()]);
    }

    public function test_variant_lock_plan_precedes_source_po_and_rejects_stale_variant_mapping(): void
    {
        [$source, $items, $variants] = $this->source(['1.000']);
        $replacement = $this->variant($variants[0]->product, ['size' => 'Replacement']);
        $queries = [];
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$queries, &$injected, $items, $replacement): void {
            $sql = $query->sql;
            if (str_contains($sql, 'from "product_variants"') && str_contains($sql, 'order by "id" asc')) {
                $queries[] = 'variant';
                if (! $injected) {
                    $injected = true;
                    DB::table('purchase_order_items')->where('id', $items[0]->id)->update(['product_variant_id' => $replacement->id]);
                }
            }
            if (str_contains($sql, 'from "purchase_orders"') && str_contains($sql, 'where "purchase_orders"."id" =')) {
                $queries[] = 'source';
            }
        });

        $this->assertServiceValidation(fn () => $this->followUp($this->admin, $source, [$this->selection($items[0])]), 'items.0.source_purchase_order_item_id');
        $this->assertTrue($injected);
        $this->assertSame(['variant', 'source'], $queries);
        $this->assertSame(1, PurchaseOrder::query()->count());
        $this->assertSame(0, PurchaseOrderItemTransfer::query()->count());
    }

    public function test_source_and_child_are_frozen_by_service_created_transfer(): void
    {
        [$source, $items] = $this->source(['1.000', '2.000']);
        $child = $this->followUp($this->admin, $source, [$this->selection($items[0])]);
        $editor = app(UpdatePurchaseOrder::class);
        foreach ([$source, $child] as $order) {
            $lines = $order->items()->orderBy('id')->get();
            $submitted = $lines->map(fn (PurchaseOrderItem $item): array => [
                'product_variant_id' => $item->product_variant_id,
                'ordered_quantity' => (string) $item->ordered_quantity,
                'expected_unit_cost' => (string) $item->expected_unit_cost,
            ])->all();
            $this->assertServiceValidation(fn () => $editor->execute($this->admin, $order, $editor->revision($order), 'Changed Supplier', null, $submitted), 'purchase_order');
        }
    }

    /** @param list<string> $quantities
     * @return array{PurchaseOrder, list<PurchaseOrderItem>, list<ProductVariant>}
     */
    private function source(array $quantities): array
    {
        $product = $this->product($this->category());
        $variants = [];
        foreach ($quantities as $quantity) {
            $variants[] = $this->variant($product, ['quantity_mode' => 'fractional']);
        }
        $order = $this->coverVariant($variants[0]);
        $first = $order->items()->sole();
        $first->ordered_quantity = $quantities[0];
        $first->save();
        $items = [$first];
        foreach (array_slice($variants, 1) as $offset => $variant) {
            $item = $first->replicate();
            $item->product_variant_id = $variant->id;
            $item->size_snapshot = $variant->size;
            $item->ordered_quantity = $quantities[$offset + 1];
            $item->save();
            $items[] = $item;
        }

        return [$order, $items, $variants];
    }

    /** @return array{source_purchase_order_item_id: int, expected_unit_cost: string} */
    private function selection(PurchaseOrderItem $item, string $cost = '20.00'): array
    {
        return ['source_purchase_order_item_id' => $item->id, 'expected_unit_cost' => $cost];
    }

    /** @param list<array<string, mixed>> $items */
    private function followUp(
        User $actor,
        PurchaseOrder $source,
        array $items,
        ?string $token = null,
        mixed $supplier = 'Supplier',
        mixed $notes = null,
    ): PurchaseOrder {
        return $this->service->execute($actor, $source, $token ?? Str::uuid()->toString(), $supplier, $notes, $items);
    }

    private function coverage(ProductVariant $variant): string
    {
        $value = DB::query()->fromSub(app(PurchaseOrderCoverageQuery::class)->aggregate(), 'coverage')
            ->where('product_variant_id', $variant->id)
            ->value('open_coverage_quantity');

        return bcadd('0.000', (string) ($value ?? '0.000'), 3);
    }
}

<?php

namespace Tests\Feature\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemTransfer;
use App\Models\User;
use App\Queries\Procurement\PurchaseOrderCoverageQuery;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PurchaseOrderTransferFoundationTest extends PurchaseOrderCreationTestCase
{
    public function test_transfer_schema_has_exact_links_uniqueness_precision_and_restrict_behavior(): void
    {
        [$source, $target, $actor] = $this->lines('1.125', '1.125');
        $columns = collect(DB::select("PRAGMA table_info('purchase_order_item_transfers')"));
        // SQLite reports DECIMAL columns with NUMERIC affinity, so inspect the
        // production migration for its exact precision while testing behavior here.
        $this->assertSame('numeric', strtolower($columns->firstWhere('name', 'quantity')->type));
        $migration = file_get_contents(database_path('migrations/2026_09_24_000002_create_purchase_order_item_transfers_table.php'));
        $this->assertStringContainsString("\$table->decimal('quantity', 14, 3)", $migration);
        $this->assertStringContainsString('CHECK (quantity > 0)', $migration);
        $this->assertStringContainsString('CHECK (source_purchase_order_item_id <> target_purchase_order_item_id)', $migration);
        $this->assertNull($columns->firstWhere('name', 'updated_at'));
        $this->assertNotNull($columns->firstWhere('name', 'created_at'));

        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('purchase_order_item_transfers')"));
        foreach ([
            'source_purchase_order_item_id' => 'purchase_order_items',
            'target_purchase_order_item_id' => 'purchase_order_items',
            'created_by' => 'users',
        ] as $column => $parent) {
            $this->assertTrue($foreignKeys->contains(fn ($key): bool => $key->from === $column
                && $key->table === $parent && $key->on_delete === 'RESTRICT' && $key->on_update === 'RESTRICT'));
        }

        $indexes = collect(DB::select("PRAGMA index_list('purchase_order_item_transfers')"));
        foreach (['source_purchase_order_item_id', 'target_purchase_order_item_id'] as $column) {
            $this->assertTrue($indexes->contains(function ($index) use ($column): bool {
                $indexedColumns = collect(DB::select("PRAGMA index_info('{$index->name}')"))->pluck('name')->all();

                return (int) $index->unique === 1 && $indexedColumns === [$column];
            }), "Missing unique index for {$column}.");
        }
        $this->assertTrue($indexes->contains(function ($index): bool {
            $indexedColumns = collect(DB::select("PRAGMA index_info('{$index->name}')"))->pluck('name')->all();

            return $indexedColumns === ['created_by', 'created_at'];
        }), 'Missing actor and creation time index.');

        foreach (['source_purchase_order_item_id', 'target_purchase_order_item_id', 'created_by'] as $column) {
            $this->assertDatabaseRejects(fn () => DB::table('purchase_order_item_transfers')->insert([
                'source_purchase_order_item_id' => $source->id,
                'target_purchase_order_item_id' => $target->id,
                'quantity' => '1.125',
                'created_by' => $actor->id,
                $column => 999999,
            ]));
        }

        $transfer = $this->transfer($source, $target, $actor, '1.125');
        [, $otherTarget] = $this->lines('1.125', '1.125');
        $this->assertDatabaseRejects(fn () => $this->transfer($source, $otherTarget, $actor, '1.125'));
        $otherSource = $this->makeItem($source->purchaseOrder, $this->variant($source->variant->product), '1.125');
        $this->assertDatabaseRejects(fn () => $this->transfer($otherSource, $target, $actor, '1.125'));
        foreach ([
            fn () => DB::table('purchase_order_items')->where('id', $source->id)->delete(),
            fn () => DB::table('purchase_order_items')->where('id', $target->id)->delete(),
            fn () => DB::table('users')->where('id', $actor->id)->delete(),
        ] as $delete) {
            $this->assertDatabaseRejects($delete);
        }
        $this->assertSame('1.125', $transfer->quantity);
    }

    public function test_relations_immutable_evidence_and_exact_outstanding_arithmetic(): void
    {
        [$source, $target, $actor] = $this->lines('1.000', '0.625');
        DB::table('restock_items')->insert(['purchase_order_item_id' => $source->id, 'quantity' => '0.125']);
        $transfer = $this->transfer($source, $target, $actor, '0.625');

        $this->assertTrue($source->outgoingTransfer->is($transfer));
        $this->assertTrue($target->incomingTransfer->is($transfer));
        $this->assertTrue($transfer->sourceItem->is($source));
        $this->assertTrue($transfer->targetItem->is($target));
        $this->assertTrue($transfer->createdBy->is($actor));
        $this->assertSame('0.125', $source->acceptedQuantity());
        $this->assertSame('0.625', $source->transferredQuantity());
        $this->assertSame('0.250', $source->outstandingQuantity());
        $this->assertSame('0.000', $target->transferredQuantity());
        $this->assertSame('0.625', $target->outstandingQuantity());

        $transfer->quantity = '0.500';
        $this->expectException(LogicException::class);
        $transfer->save();
    }

    public function test_transfer_cannot_be_deleted_and_outstanding_never_goes_negative(): void
    {
        [$source, $target, $actor] = $this->lines('1.000', '1.125');
        $transfer = $this->transfer($source, $target, $actor, '1.125');
        $this->assertSame('0.000', $source->outstandingQuantity());
        $this->assertSame('1.125', $target->outstandingQuantity());

        $this->expectException(LogicException::class);
        $transfer->delete();
    }

    public function test_coverage_is_conserved_and_terminal_lines_are_excluded(): void
    {
        [$source, $target, $actor] = $this->lines('1.000', '0.625');
        $target->purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
        $target->purchaseOrder->save();
        DB::table('restock_items')->insert(['purchase_order_item_id' => $source->id, 'quantity' => '0.375']);
        $this->assertSame('0.625', $this->coverage($source));

        $target->purchaseOrder->status = PurchaseOrder::STATUS_PENDING;
        $target->purchaseOrder->save();
        $this->transfer($source, $target, $actor, '0.625');
        $this->assertSame('0.000', $source->outstandingQuantity());
        $this->assertSame('0.625', $this->coverage($source));
        $this->assertSame('0.625', $target->outstandingQuantity());

        $target->purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
        $target->purchaseOrder->save();
        $this->assertSame('0.000', $this->coverage($source));
    }

    public function test_incoming_and_outgoing_transfer_freeze_pending_order_edits_without_partial_writes(): void
    {
        [$source, $target, $actor] = $this->lines('2.000', '2.000');
        $this->transfer($source, $target, $actor, '2.000');
        $editor = app(UpdatePurchaseOrder::class);

        foreach ([$source, $target] as $item) {
            $order = $item->purchaseOrder;
            $this->assertSame(PurchaseOrder::STATUS_PENDING, $order->status);
            $before = [$order->supplier_name, $order->notes, $item->ordered_quantity, $item->expected_unit_cost];
            $this->assertServiceValidation(fn () => $editor->execute(
                $this->admin,
                $order,
                $editor->revision($order),
                'Changed supplier',
                'Changed notes',
                [[
                    'product_variant_id' => $item->product_variant_id,
                    'ordered_quantity' => '3.000',
                    'expected_unit_cost' => '33.00',
                ]],
            ), 'purchase_order');
            $this->assertSame($before, [$order->fresh()->supplier_name, $order->fresh()->notes, $item->fresh()->ordered_quantity, $item->fresh()->expected_unit_cost]);
        }
    }

    public function test_transferred_source_and_pending_child_keep_archive_guard_active(): void
    {
        [$source, $target, $actor] = $this->lines('2.000', '2.000');
        $this->initialize($source->variant);
        $this->transfer($source, $target, $actor, '2.000');
        $variant = $source->variant;

        $this->actingAs($this->admin)->patch(route('product-variants.archive', $variant))->assertSessionHasErrors('status');
        $this->assertSame(ProductVariant::STATUS_ACTIVE, $variant->fresh()->status);

        $target->purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
        $target->purchaseOrder->save();
        $this->actingAs($this->admin)->patch(route('product-variants.archive', $variant))->assertSessionHasNoErrors();
        $this->assertSame(ProductVariant::STATUS_ARCHIVED, $variant->fresh()->status);
    }

    /** @return array{PurchaseOrderItem, PurchaseOrderItem, User} */
    private function lines(string $sourceQuantity, string $targetQuantity): array
    {
        $variant = $this->variant($this->product($this->category()), ['quantity_mode' => 'fractional']);
        $sourceOrder = $this->coverVariant($variant);
        $source = $sourceOrder->items()->sole();
        $source->ordered_quantity = $sourceQuantity;
        $source->save();

        $targetOrder = new PurchaseOrder(['supplier_name' => 'Follow-up supplier']);
        $targetOrder->parent_purchase_order_id = $sourceOrder->id;
        $targetOrder->submission_token = Str::uuid()->toString();
        $targetOrder->created_by = $this->admin->id;
        $targetOrder->save();
        $target = $source->replicate();
        $target->purchase_order_id = $targetOrder->id;
        $target->ordered_quantity = $targetQuantity;
        $target->save();

        return [$source, $target, User::factory()->admin()->create()];
    }

    private function makeItem(PurchaseOrder $order, ProductVariant $variant, string $quantity): PurchaseOrderItem
    {
        $item = $order->items()->firstOrFail()->replicate();
        $item->product_variant_id = $variant->id;
        $item->ordered_quantity = $quantity;
        $item->save();

        return $item;
    }

    private function transfer(PurchaseOrderItem $source, PurchaseOrderItem $target, User $actor, string $quantity): PurchaseOrderItemTransfer
    {
        return PurchaseOrderItemTransfer::create([
            'source_purchase_order_item_id' => $source->id,
            'target_purchase_order_item_id' => $target->id,
            'quantity' => $quantity,
            'created_by' => $actor->id,
        ]);
    }

    private function coverage(PurchaseOrderItem $item): string
    {
        $value = DB::query()->fromSub(app(PurchaseOrderCoverageQuery::class)->aggregate(), 'coverage')
            ->where('product_variant_id', $item->product_variant_id)
            ->value('open_coverage_quantity');

        return bcadd('0.000', (string) ($value ?? '0.000'), 3);
    }

    /** @param callable(): mixed $operation */
    private function assertDatabaseRejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a database constraint failure.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }
}

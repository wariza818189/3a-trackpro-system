<?php

namespace Tests\Feature\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockDamageItem;
use App\Models\StockMovement;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

final class PurchaseOrderDamageFoundationTest extends PurchaseOrderCreationTestCase
{
    public function test_schema_has_exact_snapshots_links_indexes_and_created_at_only(): void
    {
        $columns = collect(DB::select("PRAGMA table_info('restock_damage_items')"));
        $this->assertSame([
            'id', 'restock_id', 'purchase_order_item_id', 'product_variant_id',
            'product_name_snapshot', 'size_snapshot', 'type_series_snapshot',
            'thickness_snapshot', 'unit_snapshot', 'damaged_quantity',
            'damage_note', 'created_at',
        ], $columns->pluck('name')->all());
        $this->assertSame('numeric', strtolower($columns->firstWhere('name', 'damaged_quantity')->type));
        $this->assertFalse(Schema::hasColumn('restock_damage_items', 'updated_at'));

        $migration = file_get_contents(database_path('migrations/2026_09_25_000001_create_restock_damage_items_table.php'));
        $this->assertStringContainsString("\$table->decimal('damaged_quantity', 14, 3)", $migration);
        $this->assertStringContainsString('CHECK (damaged_quantity > 0)', $migration);
        $this->assertStringContainsString('CHECK (CHAR_LENGTH(TRIM(damage_note)) > 0)', $migration);

        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('restock_damage_items')"));
        foreach ([
            'restock_id' => 'restocks',
            'purchase_order_item_id' => 'purchase_order_items',
            'product_variant_id' => 'product_variants',
        ] as $column => $parent) {
            $this->assertTrue($foreignKeys->contains(fn ($key): bool => $key->from === $column
                && $key->table === $parent && $key->on_delete === 'RESTRICT' && $key->on_update === 'RESTRICT'));
        }

        $indexes = collect(DB::select("PRAGMA index_list('restock_damage_items')"));
        foreach ([
            [['restock_id', 'purchase_order_item_id'], true],
            [['purchase_order_item_id', 'created_at'], false],
            [['product_variant_id', 'created_at'], false],
        ] as [$expectedColumns, $unique]) {
            $this->assertTrue($indexes->contains(function ($index) use ($expectedColumns, $unique): bool {
                $actualColumns = collect(DB::select("PRAGMA index_info('{$index->name}')"))->pluck('name')->all();

                return $actualColumns === $expectedColumns && ((int) $index->unique === (int) $unique);
            }));
        }
    }

    public function test_damage_evidence_is_immutable_and_preserves_snapshots_without_stock_or_coverage_effects(): void
    {
        [$line, $restock, $variant] = $this->receiptLine('7.375');
        $stockBefore = $variant->current_stock;
        $movementCount = StockMovement::query()->count();
        $damage = $this->damage($line, $restock, '3.750');

        $this->assertSame('3.750', $damage->damaged_quantity);
        $this->assertSame('Damaged on delivery', $damage->damage_note);
        $this->assertNull($damage->getUpdatedAtColumn());
        $this->assertTrue($damage->restock->is($restock));
        $this->assertTrue($damage->purchaseOrderItem->is($line));
        $this->assertTrue($damage->variant->is($variant));
        $this->assertTrue($restock->damageItems->sole()->is($damage));
        $this->assertTrue($line->damageItems->sole()->is($damage));
        $this->assertTrue($variant->restockDamageItems->sole()->is($damage));
        $this->assertTrue($damage->restock->recordedBy->is($this->admin));
        $this->assertNotNull($damage->created_at);

        $variant->product->name = 'Renamed catalog product';
        $variant->product->save();
        $variant->size = 'Renamed catalog size';
        $variant->status = ProductVariant::STATUS_ARCHIVED;
        $variant->save();
        $this->assertSame($line->product_name_snapshot, $damage->fresh()->product_name_snapshot);
        $this->assertSame($line->size_snapshot, $damage->fresh()->size_snapshot);
        $this->assertSame($stockBefore, $variant->fresh()->current_stock);
        $this->assertSame($movementCount, StockMovement::query()->count());
        $this->assertSame('0.000', $line->acceptedQuantity());
        $this->assertSame('0.000', $line->transferredQuantity());
        $this->assertSame('7.375', $line->outstandingQuantity());

        foreach (['save', 'delete'] as $method) {
            $persisted = $damage->fresh();
            if ($method === 'save') {
                $persisted->damage_note = 'Changed';
            }
            try {
                $persisted->$method();
                $this->fail('Damage history allowed '.$method);
            } catch (LogicException $exception) {
                $this->assertStringStartsWith('Historical records cannot be ', $exception->getMessage());
            }
        }
    }

    public function test_foreign_keys_unique_line_positive_quantity_and_nonblank_note_are_enforced(): void
    {
        [$line, $restock, $variant] = $this->receiptLine();
        $valid = $this->attributes($line, $restock);

        foreach (['restock_id', 'purchase_order_item_id', 'product_variant_id'] as $column) {
            $this->assertRejected(fn () => DB::table('restock_damage_items')->insert(array_replace($valid, [$column => 999999])));
        }
        foreach (['0.000', '-0.001'] as $quantity) {
            $this->assertRejected(fn () => DB::table('restock_damage_items')->insert(array_replace($valid, ['damaged_quantity' => $quantity])));
        }
        $this->assertRejected(fn () => DB::table('restock_damage_items')->insert(array_replace($valid, ['damage_note' => '   '])));

        $this->damage($line, $restock);
        $this->assertRejected(fn () => DB::table('restock_damage_items')->insert($valid));
        $secondRestock = $this->newRestock($line);
        $this->assertSame('1.000', $this->damage($line, $secondRestock)->damaged_quantity);
        foreach ([
            fn () => DB::table('restocks')->where('id', $restock->id)->delete(),
            fn () => DB::table('purchase_order_items')->where('id', $line->id)->delete(),
            fn () => DB::table('product_variants')->where('id', $variant->id)->delete(),
            fn () => DB::table('restocks')->where('id', $restock->id)->update(['id' => 999999]),
        ] as $change) {
            $this->assertRejected($change);
        }
    }

    public function test_damage_only_freezes_pending_purchase_order_edits(): void
    {
        [$line, $restock] = $this->receiptLine();
        $this->damage($line, $restock);
        $purchaseOrder = $line->purchaseOrder;
        $beforeSupplier = $purchaseOrder->supplier_name;
        $update = app(UpdatePurchaseOrder::class);

        $this->assertTrue($purchaseOrder->isEditable());
        $this->assertServiceValidation(fn () => $update->execute(
            $this->admin,
            $purchaseOrder,
            $update->revision($purchaseOrder),
            'Changed supplier',
            null,
            [[
                'product_variant_id' => $line->product_variant_id,
                'ordered_quantity' => '8.000',
                'expected_unit_cost' => '25.50',
            ]],
        ), 'purchase_order');
        $this->assertSame($beforeSupplier, $purchaseOrder->fresh()->supplier_name);
        $this->assertSame('2.000', $line->outstandingQuantity());
    }

    /** @return array{PurchaseOrderItem, Restock, ProductVariant} */
    private function receiptLine(string $orderedQuantity = '2.000'): array
    {
        $variant = $this->variant($this->product($this->category()), [
            'unit' => 'kg', 'quantity_mode' => 'fractional',
        ]);
        $purchaseOrder = $this->coverVariant($variant);
        $line = $purchaseOrder->items()->sole();
        $line->ordered_quantity = $orderedQuantity;
        $line->save();

        return [$line, $this->newRestock($line), $variant];
    }

    private function newRestock(PurchaseOrderItem $line): Restock
    {
        $restock = new Restock;
        $restock->submission_token = Str::uuid()->toString();
        $restock->recorded_by = $this->admin->id;
        $restock->purchase_order_id = $line->purchase_order_id;
        $restock->total_cost = '0.00';
        $restock->save();

        return $restock;
    }

    /** @return array<string, int|string> */
    private function attributes(PurchaseOrderItem $line, Restock $restock, string $quantity = '1.000'): array
    {
        return [
            'restock_id' => $restock->id,
            'purchase_order_item_id' => $line->id,
            'product_variant_id' => $line->product_variant_id,
            'product_name_snapshot' => $line->product_name_snapshot,
            'size_snapshot' => $line->size_snapshot,
            'type_series_snapshot' => $line->type_series_snapshot,
            'thickness_snapshot' => $line->thickness_snapshot,
            'unit_snapshot' => $line->unit_snapshot,
            'damaged_quantity' => $quantity,
            'damage_note' => 'Damaged on delivery',
        ];
    }

    private function damage(PurchaseOrderItem $line, Restock $restock, string $quantity = '1.000'): RestockDamageItem
    {
        return RestockDamageItem::create($this->attributes($line, $restock, $quantity));
    }

    private function assertRejected(callable $change): void
    {
        try {
            $change();
            $this->fail('Expected a database constraint rejection.');
        } catch (QueryException) {
            // The database rejected invalid immutable evidence.
        }
    }
}

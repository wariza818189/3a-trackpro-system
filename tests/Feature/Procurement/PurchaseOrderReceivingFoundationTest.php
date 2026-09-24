<?php

namespace Tests\Feature\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PurchaseOrderReceivingFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id');
            $table->decimal('ordered_quantity', 14, 3);
        });
        Schema::create('restocks', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('restock_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restock_id');
            $table->decimal('quantity', 14, 3);
        });

        (require database_path('migrations/2026_09_24_000001_add_purchase_order_links_to_restocks.php'))->up();
    }

    public function test_links_are_nullable_indexed_and_restrict_missing_parents(): void
    {
        $this->assertTrue(Schema::hasColumn('restocks', 'purchase_order_id'));
        $this->assertTrue(Schema::hasColumn('restock_items', 'purchase_order_item_id'));
        foreach ([
            ['restocks', 'purchase_order_id', 'purchase_orders'],
            ['restock_items', 'purchase_order_item_id', 'purchase_order_items'],
        ] as [$table, $column, $parent]) {
            $columns = collect(DB::select("PRAGMA table_info('{$table}')"));
            $this->assertSame(0, $columns->firstWhere('name', $column)->notnull);
            $indexes = collect(DB::select("PRAGMA index_list('{$table}')"));
            $this->assertTrue($indexes->contains(fn ($index): bool => str_contains($index->name, $column)));
            $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('{$table}')"));
            $this->assertTrue($foreignKeys->contains(fn ($key): bool => $key->from === $column
                && $key->table === $parent && $key->on_delete === 'RESTRICT' && $key->on_update === 'RESTRICT'));
        }

        $legacyRestockId = DB::table('restocks')->insertGetId([]);
        DB::table('restock_items')->insert(['restock_id' => $legacyRestockId, 'quantity' => '1.000']);
        $this->assertNull(DB::table('restocks')->value('purchase_order_id'));
        $this->assertNull(DB::table('restock_items')->value('purchase_order_item_id'));
    }

    public function test_relations_and_multiple_receipts_produce_exact_accepted_and_outstanding_quantities(): void
    {
        $poId = DB::table('purchase_orders')->insertGetId([]);
        $lineId = DB::table('purchase_order_items')->insertGetId([
            'purchase_order_id' => $poId, 'ordered_quantity' => '1.000',
        ]);
        foreach (['0.125', '0.250'] as $quantity) {
            $restockId = DB::table('restocks')->insertGetId(['purchase_order_id' => $poId]);
            DB::table('restock_items')->insert([
                'restock_id' => $restockId,
                'purchase_order_item_id' => $lineId,
                'quantity' => $quantity,
            ]);
        }

        $po = PurchaseOrder::query()->findOrFail($poId);
        $line = PurchaseOrderItem::query()->findOrFail($lineId);
        $this->assertCount(2, $po->restocks);
        $this->assertCount(2, $line->restockItems);
        $this->assertTrue($po->restocks->first()->purchaseOrder->is($po));
        $this->assertTrue($line->restockItems->first()->purchaseOrderItem->is($line));
        $this->assertSame('0.375', $line->acceptedQuantity());
        $this->assertSame('0.625', $line->outstandingQuantity());

        $legacy = Restock::query()->findOrFail(DB::table('restocks')->insertGetId([]));
        $legacyItem = RestockItem::query()->findOrFail(DB::table('restock_items')->insertGetId([
            'restock_id' => $legacy->id, 'quantity' => '2.000',
        ]));
        $this->assertNull($legacy->purchaseOrder);
        $this->assertNull($legacyItem->purchaseOrderItem);
    }
}

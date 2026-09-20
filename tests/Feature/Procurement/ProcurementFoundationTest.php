<?php

namespace Tests\Feature\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class ProcurementFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Procurement foundation tests require the in-memory SQLite database.');
        }

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();

        // Behavior-only SQLite scaffolding. Production ENUM/CHECK/index DDL is
        // authoritative and belongs to the guarded Procurement25 MySQL test.
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('submission_token')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('supplier_name', 150);
            $table->string('status')->default(PurchaseOrder::STATUS_PENDING);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name_snapshot', 150);
            $table->string('size_snapshot', 80)->default('');
            $table->string('type_series_snapshot', 80)->default('');
            $table->string('thickness_snapshot', 40)->default('');
            $table->string('unit_snapshot', 30);
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('expected_unit_cost', 12, 2);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'product_variant_id']);
        });
    }

    public function test_purchase_order_snapshots_items_and_parent_relationships_persist(): void
    {
        $creator = User::factory()->admin()->create();
        $variantIds = [
            DB::table('product_variants')->insertGetId(['created_at' => now(), 'updated_at' => now()]),
            DB::table('product_variants')->insertGetId(['created_at' => now(), 'updated_at' => now()]),
        ];

        $parent = new PurchaseOrder([
            'supplier_name' => 'Primary Supplier',
            'notes' => 'Initial order',
        ]);
        $parent->submission_token = (string) Str::uuid();
        $parent->created_by = $creator->id;
        $parent->save();

        $child = new PurchaseOrder([
            'supplier_name' => 'Follow-up Supplier',
            'notes' => null,
        ]);
        $child->submission_token = (string) Str::uuid();
        $child->created_by = $creator->id;
        $child->parent_purchase_order_id = $parent->id;
        $child->save();

        foreach ([
            [$variantIds[0], 'Roofing Sheet', '8 ft', 'Corrugated', '0.4 mm', 'sheet', '1.250', '500.00'],
            [$variantIds[1], 'Steel Bar', '10 mm', 'Grade 40', '', 'piece', '3.000', '0.00'],
        ] as [$variantId, $name, $size, $series, $thickness, $unit, $quantity, $cost]) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $parent->id,
                'product_variant_id' => $variantId,
                'product_name_snapshot' => $name,
                'size_snapshot' => $size,
                'type_series_snapshot' => $series,
                'thickness_snapshot' => $thickness,
                'unit_snapshot' => $unit,
                'ordered_quantity' => $quantity,
                'expected_unit_cost' => $cost,
            ]);
        }

        $reloadedParent = PurchaseOrder::query()->with(['createdBy', 'items.variant', 'children'])->findOrFail($parent->id);
        $reloadedChild = PurchaseOrder::query()->with('parent')->findOrFail($child->id);

        $this->assertTrue($reloadedParent->createdBy->is($creator));
        $this->assertCount(2, $reloadedParent->items);
        $this->assertTrue($reloadedParent->items[0]->purchaseOrder->is($reloadedParent));
        $this->assertInstanceOf(ProductVariant::class, $reloadedParent->items[0]->variant);
        $this->assertSame('Roofing Sheet', $reloadedParent->items[0]->product_name_snapshot);
        $this->assertSame('8 ft', $reloadedParent->items[0]->size_snapshot);
        $this->assertSame('Corrugated', $reloadedParent->items[0]->type_series_snapshot);
        $this->assertSame('0.4 mm', $reloadedParent->items[0]->thickness_snapshot);
        $this->assertSame('sheet', $reloadedParent->items[0]->unit_snapshot);
        $this->assertSame('1.250', $reloadedParent->items[0]->ordered_quantity);
        $this->assertSame('500.00', $reloadedParent->items[0]->expected_unit_cost);
        $this->assertSame('0.00', $reloadedParent->items[1]->expected_unit_cost);
        $this->assertTrue($reloadedChild->parent->is($reloadedParent));
        $this->assertCount(1, $reloadedParent->children);
        $this->assertTrue($reloadedParent->children[0]->is($reloadedChild));
        $this->assertCount(2, $creator->purchaseOrders);
        $this->assertCount(1, ProductVariant::query()->findOrFail($variantIds[0])->purchaseOrderItems);
    }
}

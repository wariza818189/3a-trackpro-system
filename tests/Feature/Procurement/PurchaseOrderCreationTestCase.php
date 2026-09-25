<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

abstract class PurchaseOrderCreationTestCase extends TestCase
{
    protected User $admin;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Purchase Order creation tests require the in-memory SQLite database.');
        }

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_09_05_000001_create_categories_table.php'))->up();
        (require database_path('migrations/2026_09_05_000002_create_products_table.php'))->up();

        // Behavior-only SQLite scaffolding. Production ENUM/CHECK/index DDL and
        // MySQL transaction behavior remain authoritative in the guarded suites.
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $table->string('size', 80)->default('');
            $table->string('type_series', 80)->default('');
            $table->string('thickness', 40)->default('');
            $table->string('unit', 30);
            $table->string('quantity_mode')->default('whole');
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2);
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('low_stock_threshold', 14, 3)->default(0);
            $table->string('status')->default(ProductVariant::STATUS_ACTIVE);
            $table->timestamps();
            $table->unique(['product_id', 'size', 'type_series', 'thickness', 'unit']);
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('movement_type');
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_change', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('restock_item_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['product_variant_id', 'movement_type']);
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
        Schema::create('purchase_order_item_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('target_purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->decimal('quantity', 14, 3);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('created_at')->nullable();
            $table->unique('source_purchase_order_item_id');
            $table->unique('target_purchase_order_item_id');
            $table->index(['created_by', 'created_at']);
        });
        Schema::create('restocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('submission_token')->nullable()->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->text('reference_text')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_cost', 16, 2)->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('restock_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restock_id')->nullable()->constrained('restocks')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name_snapshot')->nullable();
            $table->string('size_snapshot')->nullable();
            $table->string('type_series_snapshot')->nullable();
            $table->string('thickness_snapshot')->nullable();
            $table->string('unit_snapshot')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('line_total', 16, 2)->nullable();
        });

        // SQLite cannot ALTER TABLE ADD CONSTRAINT, so mirror the new
        // production migration's checks inline for behavior tests.
        DB::statement(<<<'SQL'
            CREATE TABLE restock_damage_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restock_id INTEGER NOT NULL REFERENCES restocks(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                purchase_order_item_id INTEGER NOT NULL REFERENCES purchase_order_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                product_variant_id INTEGER NOT NULL REFERENCES product_variants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                product_name_snapshot VARCHAR(150) NOT NULL,
                size_snapshot VARCHAR(80) NOT NULL DEFAULT '',
                type_series_snapshot VARCHAR(80) NOT NULL DEFAULT '',
                thickness_snapshot VARCHAR(40) NOT NULL DEFAULT '',
                unit_snapshot VARCHAR(30) NOT NULL,
                damaged_quantity NUMERIC NOT NULL CHECK (damaged_quantity > 0),
                damage_note TEXT NOT NULL CHECK (LENGTH(TRIM(damage_note)) > 0),
                created_at DATETIME NULL
            )
            SQL);
        Schema::table('restock_damage_items', function (Blueprint $table): void {
            $table->unique(['restock_id', 'purchase_order_item_id']);
            $table->index(['purchase_order_item_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
        });

        $this->admin = User::factory()->admin()->create();
    }

    protected function category(array $attributes = []): Category
    {
        $this->sequence++;
        $category = new Category(['name' => $attributes['name'] ?? "Category {$this->sequence}"]);
        $category->status = $attributes['status'] ?? Category::STATUS_ACTIVE;
        $category->save();

        return $category;
    }

    protected function product(Category $category, array $attributes = []): Product
    {
        $this->sequence++;
        $product = new Product(['name' => $attributes['name'] ?? "Product {$this->sequence}"]);
        $product->category_id = $category->getKey();
        $product->status = $attributes['status'] ?? Product::STATUS_ACTIVE;
        $product->save();

        return $product;
    }

    protected function variant(Product $product, array $attributes = []): ProductVariant
    {
        $this->sequence++;
        $variant = new ProductVariant([
            'size' => $attributes['size'] ?? "Size {$this->sequence}",
            'type_series' => $attributes['type_series'] ?? 'Series A',
            'thickness' => $attributes['thickness'] ?? '',
            'unit' => $attributes['unit'] ?? 'piece',
            'quantity_mode' => $attributes['quantity_mode'] ?? 'whole',
            'cost_price' => $attributes['cost_price'] ?? '40.00',
            'selling_price' => $attributes['selling_price'] ?? '60.00',
            'low_stock_threshold' => $attributes['low_stock_threshold'] ?? '5.000',
        ]);
        $variant->product_id = $product->getKey();
        $variant->current_stock = $attributes['current_stock'] ?? '0.000';
        $variant->status = $attributes['status'] ?? ProductVariant::STATUS_ACTIVE;
        $variant->save();

        return $variant;
    }

    protected function initialize(ProductVariant $variant, ?User $actor = null, string $quantity = '0.000'): StockMovement
    {
        $movement = new StockMovement;
        $movement->product_variant_id = $variant->getKey();
        $movement->movement_type = StockMovement::TYPE_INITIAL_STOCK;
        $movement->quantity_before = '0.000';
        $movement->quantity_change = $quantity;
        $movement->quantity_after = $quantity;
        $movement->performed_by = ($actor ?? $this->admin)->getKey();
        $movement->sale_item_id = null;
        $movement->restock_item_id = null;
        $movement->reason = 'Test opening inventory';
        $movement->save();

        return $movement;
    }

    /** @return array<string, mixed> */
    protected function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace([
            'submission_token' => Str::uuid()->toString(),
            'supplier_name' => 'Sample Supplier',
            'notes' => 'Test planning note',
            'items' => [[
                'product_variant_id' => $variant->getKey(),
                'ordered_quantity' => '2',
                'expected_unit_cost' => '25.50',
            ]],
        ], $overrides);
    }

    protected function coverVariant(ProductVariant $variant, ?User $actor = null): PurchaseOrder
    {
        $purchaseOrder = new PurchaseOrder;
        $purchaseOrder->parent_purchase_order_id = null;
        $purchaseOrder->submission_token = Str::uuid()->toString();
        $purchaseOrder->created_by = ($actor ?? $this->admin)->getKey();
        $purchaseOrder->supplier_name = 'Existing Supplier';
        $purchaseOrder->status = PurchaseOrder::STATUS_PENDING;
        $purchaseOrder->notes = null;
        $purchaseOrder->save();

        $item = new PurchaseOrderItem;
        $item->purchase_order_id = $purchaseOrder->getKey();
        $item->product_variant_id = $variant->getKey();
        $item->product_name_snapshot = $variant->product->name;
        $item->size_snapshot = $variant->size;
        $item->type_series_snapshot = $variant->type_series;
        $item->thickness_snapshot = $variant->thickness;
        $item->unit_snapshot = $variant->unit;
        $item->ordered_quantity = '5.000';
        $item->expected_unit_cost = '20.00';
        $item->save();

        return $purchaseOrder;
    }

    /** @param callable(): mixed $callback */
    protected function assertServiceValidation(callable $callback, string $key): ValidationException
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());

            return $exception;
        }

        $this->fail("Expected validation error for {$key}.");
    }
}

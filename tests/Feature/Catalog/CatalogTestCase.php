<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

abstract class CatalogTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Catalog feature tests require the in-memory SQLite database.');
        }

        $this->withoutVite();

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_09_05_000001_create_categories_table.php'))->up();
        (require database_path('migrations/2026_09_05_000002_create_products_table.php'))->up();

        // Behavior-test scaffolding only. Production ProductVariant CHECK DDL remains
        // MySQL-specific and is verified exclusively by the guarded MySQL suite.
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
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['product_id', 'size', 'type_series', 'thickness', 'unit']);
        });

        // Minimal sale/restock history markers for behavior tests, not production-schema proof.
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('checkout_token')->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('status')->default('completed');
            $table->decimal('total_amount', 16, 2);
            $table->decimal('cash_received', 16, 2);
            $table->decimal('change_amount', 16, 2);
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
        });
        Schema::create('restock_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
        });

        // Opening-inventory behavior scaffolding only. Production StockMovement
        // ENUM/CHECK/index DDL remains exclusively covered by the guarded MySQL suite.
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('movement_type')->default('CORRECTION');
            $table->decimal('quantity_before', 14, 3)->default(0);
            $table->decimal('quantity_change', 14, 3)->default(0);
            $table->decimal('quantity_after', 14, 3)->default(0);
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('restock_item_id')->nullable()->constrained('restock_items')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['product_variant_id', 'movement_type']);
        });
    }

    protected function category(array $attributes = []): Category
    {
        $category = new Category(array_merge(['name' => 'Fasteners'], $attributes));
        $category->status = $attributes['status'] ?? Category::STATUS_ACTIVE;
        $category->save();

        return $category;
    }

    protected function product(Category $category, array $attributes = []): Product
    {
        $product = new Product(array_merge(['name' => 'Machine Bolt'], $attributes));
        $product->category_id = $category->id;
        $product->status = $attributes['status'] ?? Product::STATUS_ACTIVE;
        $product->save();

        return $product;
    }

    protected function variant(Product $product, array $attributes = []): ProductVariant
    {
        $variant = new ProductVariant(array_merge($this->validVariant(), $attributes));
        $variant->product_id = $product->id;
        $variant->status = $attributes['status'] ?? ProductVariant::STATUS_ACTIVE;
        if (array_key_exists('current_stock', $attributes)) {
            $variant->current_stock = $attributes['current_stock'];
        }
        $variant->save();

        return $variant;
    }

    protected function validVariant(array $overrides = []): array
    {
        return array_merge([
            'size' => 'M8',
            'type_series' => 'Grade 8.8',
            'thickness' => '',
            'unit' => 'piece',
            'quantity_mode' => 'whole',
            'cost_price' => '50.00',
            'selling_price' => '75.00',
            'low_stock_threshold' => '2.000',
        ], $overrides);
    }
}

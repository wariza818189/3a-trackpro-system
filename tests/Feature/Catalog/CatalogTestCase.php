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

        // Minimal history markers for catalog behavior tests, not production-schema proof.
        foreach (['sale_items', 'restock_items', 'stock_movements'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
                $table->id();
                $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
                if ($tableName === 'stock_movements') {
                    $table->string('movement_type')->default('CORRECTION');
                }
            });
        }
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

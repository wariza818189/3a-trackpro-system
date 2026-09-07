<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

abstract class PosTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('POS feature tests require the in-memory SQLite database.');
        }

        $this->withoutVite();
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_09_05_000001_create_categories_table.php'))->up();
        (require database_path('migrations/2026_09_05_000002_create_products_table.php'))->up();

        // Behavior-only SQLite scaffolding. MySQL constraints and concurrency are
        // verified solely by the guarded MySQL suite.
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
        });
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
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name_snapshot', 150);
            $table->string('size_snapshot', 80)->default('');
            $table->string('type_series_snapshot', 80)->default('');
            $table->string('thickness_snapshot', 40)->default('');
            $table->string('unit_snapshot', 30);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestamp('created_at')->nullable();
            $table->unique(['sale_id', 'product_variant_id']);
        });
        Schema::create('restocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recorded_by')->constrained('users');
        });
        Schema::create('restock_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restock_id')->constrained('restocks');
            $table->foreignId('product_variant_id')->constrained('product_variants');
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('movement_type');
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_change', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('restock_item_id')->nullable()->constrained('restock_items')->restrictOnDelete()->restrictOnUpdate();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['sale_item_id', 'movement_type']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('before_values')->nullable();
            $table->text('after_values')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function category(array $attributes = []): Category
    {
        $category = new Category(['name' => $attributes['name'] ?? 'POS Category '.Str::random(8)]);
        $category->status = $attributes['status'] ?? Category::STATUS_ACTIVE;
        $category->save();

        return $category;
    }

    protected function product(Category $category, array $attributes = []): Product
    {
        $product = new Product(['name' => $attributes['name'] ?? 'POS Product '.Str::random(8)]);
        $product->category_id = $category->id;
        $product->status = $attributes['status'] ?? Product::STATUS_ACTIVE;
        $product->save();

        return $product;
    }

    protected function variant(Product $product, array $attributes = []): ProductVariant
    {
        $variant = new ProductVariant(array_merge([
            'size' => '16oz',
            'type_series' => 'Claw',
            'thickness' => '',
            'unit' => 'piece',
            'quantity_mode' => 'whole',
            'cost_price' => '70.00',
            'selling_price' => '100.00',
            'low_stock_threshold' => '1.000',
        ], $attributes));
        $variant->product_id = $product->id;
        $variant->status = $attributes['status'] ?? ProductVariant::STATUS_ACTIVE;
        $variant->current_stock = $attributes['current_stock'] ?? '10.000';
        $variant->save();

        return $variant;
    }

    protected function initialize(ProductVariant $variant, User $actor): void
    {
        $stock = (string) $variant->current_stock;
        DB::table('stock_movements')->insert([
            'product_variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_INITIAL_STOCK,
            'quantity_before' => '0.000',
            'quantity_change' => $stock,
            'quantity_after' => $stock,
            'performed_by' => $actor->id,
            'sale_item_id' => null,
            'restock_item_id' => null,
            'reason' => 'POS test opening count',
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'submission_token' => Str::uuid()->toString(),
            'amount_tendered' => '200.00',
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => '1',
                'expected_unit_price' => (string) $variant->selling_price,
            ]],
        ], $overrides);
    }

    protected function initializedVariant(User $actor, array $variantAttributes = [], array $productAttributes = [], array $categoryAttributes = []): ProductVariant
    {
        $variant = $this->variant(
            $this->product($this->category($categoryAttributes), $productAttributes),
            $variantAttributes,
        );
        $this->initialize($variant, $actor);

        return $variant;
    }
}

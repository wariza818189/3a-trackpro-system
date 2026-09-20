<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Queries\Procurement\LowStockPurchaseOrderRecommendations;
use App\Queries\Procurement\ProcurementVariantCatalogQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class ProcurementRecommendationsTest extends TestCase
{
    private User $actor;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Procurement recommendation tests require the in-memory SQLite database.');
        }

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_09_05_000001_create_categories_table.php'))->up();
        (require database_path('migrations/2026_09_05_000002_create_products_table.php'))->up();

        // Behavior-only SQLite scaffolding. The guarded #25A MySQL test remains
        // authoritative for production ENUM, CHECK, FK, and index DDL.
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

        $this->actor = User::factory()->admin()->create();
    }

    public function test_base_catalog_requires_active_initialized_hierarchy_and_keeps_healthy_variants(): void
    {
        $activeProduct = $this->product($this->category());
        $low = $this->variant($activeProduct, [
            'size' => 'Low',
            'current_stock' => '1.000',
            'low_stock_threshold' => '2.000',
        ]);
        $healthy = $this->variant($activeProduct, [
            'size' => 'Healthy',
            'current_stock' => '5.000',
            'low_stock_threshold' => '2.000',
        ]);
        $uninitialized = $this->variant($activeProduct, ['size' => 'Uninitialized']);
        $inactiveVariant = $this->variant($activeProduct, [
            'size' => 'Inactive Variant',
            'status' => ProductVariant::STATUS_ARCHIVED,
        ]);
        $inactiveProductVariant = $this->variant($this->product($this->category(), [
            'status' => Product::STATUS_ARCHIVED,
        ]), ['size' => 'Inactive Product']);
        $inactiveCategoryVariant = $this->variant($this->product($this->category([
            'status' => Category::STATUS_ARCHIVED,
        ])), ['size' => 'Inactive Category']);

        foreach ([$low, $healthy, $inactiveVariant, $inactiveProductVariant, $inactiveCategoryVariant] as $variant) {
            $this->initialize($variant);
        }

        $catalogIds = app(ProcurementVariantCatalogQuery::class)->query()
            ->orderBy('product_variants.id')
            ->pluck('product_variants.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$low->id, $healthy->id], $catalogIds);
        $this->assertTrue(ProductVariant::query()->lowStock()->whereKey($uninitialized->id)->exists());
        $this->assertFalse(ProductVariant::query()->initialized()->whereKey($uninitialized->id)->exists());
        $this->assertContains($healthy->id, $catalogIds, 'Healthy initialized Variants remain manually procurement-selectable.');
    }

    public function test_zero_initial_stock_is_authoritative_and_uncovered_result_contract_has_no_reorder_quantity(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $variant = $this->variant($product, [
            'size' => 'Initialized Zero',
            'current_stock' => '0.000',
            'low_stock_threshold' => '0.000',
        ]);
        $this->initialize($variant, '0.000');

        $recommendations = app(LowStockPurchaseOrderRecommendations::class);
        $result = $recommendations->all()->sole();

        $this->assertSame($variant->id, $result->id);
        $this->assertSame($product->id, $result->product_id);
        $this->assertSame($product->name, $result->product->name);
        $this->assertSame($category->id, $result->product->category->id);
        $this->assertSame($category->name, $result->product->category->name);
        $this->assertSame('0.000', $result->current_stock);
        $this->assertSame('0.000', $result->low_stock_threshold);
        $this->assertSame('0.000', $result->open_coverage_quantity);
        $this->assertSame('uncovered', $result->coverage_state);
        $this->assertSame([$variant->id], $recommendations->uncovered()->pluck('product_variants.id')->all());
        $this->assertSame([], $recommendations->covered()->pluck('product_variants.id')->all());

        foreach (['suggested_order_quantity', 'reorder_quantity', 'shortage', 'accepted_quantity', 'transferred_quantity', 'damage_quantity'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $result->getAttributes());
        }
        $this->assertArrayNotHasKey('cost_price', $result->getAttributes());
        $this->assertArrayNotHasKey('selling_price', $result->getAttributes());
        $this->assertArrayNotHasKey('submission_token', $result->getAttributes());
    }

    public function test_open_statuses_sum_exact_decimal_coverage_and_parent_linkage_has_no_special_treatment(): void
    {
        $variant = $this->variant($this->product($this->category()), [
            'size' => 'Fractional Coverage',
            'unit' => 'kg',
            'quantity_mode' => 'fractional',
            'current_stock' => '0.125',
            'low_stock_threshold' => '1.000',
        ]);
        $this->initialize($variant);

        $pending = $this->purchaseOrder(PurchaseOrder::STATUS_PENDING);
        $partiallyReceivedChild = $this->purchaseOrder(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $pending);
        $completed = $this->purchaseOrder(PurchaseOrder::STATUS_COMPLETED);
        $closed = $this->purchaseOrder(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER);

        $this->purchaseOrderItem($pending, $variant, '1.125');
        // Until #26/#27 evidence exists, partially received coverage deliberately
        // uses the full ordered quantity and therefore may overstate outstanding demand.
        $this->purchaseOrderItem($partiallyReceivedChild, $variant, '2.250');
        $this->purchaseOrderItem($completed, $variant, '10.000');
        $this->purchaseOrderItem($closed, $variant, '20.000');

        $recommendations = app(LowStockPurchaseOrderRecommendations::class);
        $result = $recommendations->all()->sole();

        $this->assertSame([
            PurchaseOrder::STATUS_PENDING,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ], PurchaseOrder::OPEN_STATUSES);
        $this->assertSame('3.375', $result->open_coverage_quantity);
        $this->assertSame('covered', $result->coverage_state);
        $this->assertSame([$variant->id], $recommendations->covered()->pluck('product_variants.id')->all());
        $this->assertSame([], $recommendations->uncovered()->pluck('product_variants.id')->all());
    }

    public function test_terminal_purchase_orders_alone_leave_a_low_stock_variant_uncovered(): void
    {
        $variant = $this->variant($this->product($this->category()), [
            'size' => 'Terminal Only',
            'current_stock' => '1.000',
            'low_stock_threshold' => '1.000',
        ]);
        $this->initialize($variant);
        $this->purchaseOrderItem($this->purchaseOrder(PurchaseOrder::STATUS_COMPLETED), $variant, '4.000');
        $this->purchaseOrderItem($this->purchaseOrder(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER), $variant, '6.000');

        $recommendations = app(LowStockPurchaseOrderRecommendations::class);
        $result = $recommendations->all()->sole();

        $this->assertSame('0.000', $result->open_coverage_quantity);
        $this->assertSame('uncovered', $result->coverage_state);
        $this->assertSame([$variant->id], $recommendations->uncovered()->pluck('product_variants.id')->all());
        $this->assertSame([], $recommendations->covered()->pluck('product_variants.id')->all());
    }

    public function test_recommendations_exclude_healthy_variants_but_keep_covered_low_stock_visible(): void
    {
        $product = $this->product($this->category());
        $healthy = $this->variant($product, [
            'size' => 'Healthy',
            'current_stock' => '3.001',
            'low_stock_threshold' => '3.000',
        ]);
        $coveredLow = $this->variant($product, [
            'size' => 'Covered Low',
            'current_stock' => '3.000',
            'low_stock_threshold' => '3.000',
        ]);
        $this->initialize($healthy);
        $this->initialize($coveredLow);
        $this->purchaseOrderItem($this->purchaseOrder(PurchaseOrder::STATUS_PENDING), $coveredLow, '5.000');

        $catalogIds = app(ProcurementVariantCatalogQuery::class)->query()->pluck('product_variants.id')->all();
        $recommendations = app(LowStockPurchaseOrderRecommendations::class);

        $this->assertContains($healthy->id, $catalogIds);
        $this->assertContains($coveredLow->id, $catalogIds);
        $this->assertSame([$coveredLow->id], $recommendations->all()->pluck('product_variants.id')->all());
        $this->assertSame([$coveredLow->id], $recommendations->covered()->pluck('product_variants.id')->all());
    }

    public function test_recommendation_order_is_uncovered_then_stock_then_stable_identity(): void
    {
        $firstProduct = $this->product($this->category());
        $secondProduct = $this->product($this->category());

        $lowerStock = $this->variant($firstProduct, [
            'size' => 'M', 'current_stock' => '1.000', 'low_stock_threshold' => '5.000',
        ]);
        $firstProductTypeA = $this->variant($firstProduct, [
            'size' => 'K', 'type_series' => 'A', 'current_stock' => '2.000', 'low_stock_threshold' => '5.000',
        ]);
        $firstProductTypeB = $this->variant($firstProduct, [
            'size' => 'K', 'type_series' => 'B', 'current_stock' => '2.000', 'low_stock_threshold' => '5.000',
        ]);
        $secondProductEarlierDescriptor = $this->variant($secondProduct, [
            'size' => 'A', 'current_stock' => '2.000', 'low_stock_threshold' => '5.000',
        ]);
        $coveredZero = $this->variant($firstProduct, [
            'size' => 'Covered', 'current_stock' => '0.000', 'low_stock_threshold' => '5.000',
        ]);

        foreach ([$lowerStock, $firstProductTypeA, $firstProductTypeB, $secondProductEarlierDescriptor, $coveredZero] as $variant) {
            $this->initialize($variant);
        }
        $this->purchaseOrderItem($this->purchaseOrder(PurchaseOrder::STATUS_PENDING), $coveredZero, '1.000');

        $recommendations = app(LowStockPurchaseOrderRecommendations::class);
        $expectedUncovered = [
            $lowerStock->id,
            $firstProductTypeA->id,
            $firstProductTypeB->id,
            $secondProductEarlierDescriptor->id,
        ];

        $this->assertSame(
            [...$expectedUncovered, $coveredZero->id],
            $recommendations->all()->pluck('product_variants.id')->all(),
        );
        $this->assertSame($expectedUncovered, $recommendations->uncovered()->pluck('product_variants.id')->all());
        $this->assertSame([$coveredZero->id], $recommendations->covered()->pluck('product_variants.id')->all());
    }

    private function category(array $attributes = []): Category
    {
        $category = new Category(['name' => $attributes['name'] ?? 'Procurement Category '.++$this->sequence]);
        $category->status = $attributes['status'] ?? Category::STATUS_ACTIVE;
        $category->save();

        return $category;
    }

    private function product(Category $category, array $attributes = []): Product
    {
        $product = new Product(['name' => $attributes['name'] ?? 'Procurement Product '.++$this->sequence]);
        $product->category_id = $category->id;
        $product->status = $attributes['status'] ?? Product::STATUS_ACTIVE;
        $product->save();

        return $product;
    }

    private function variant(Product $product, array $attributes = []): ProductVariant
    {
        $variant = new ProductVariant(array_merge([
            'size' => 'Variant '.++$this->sequence,
            'type_series' => '',
            'thickness' => '',
            'unit' => 'piece',
            'quantity_mode' => 'whole',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'low_stock_threshold' => '2.000',
        ], $attributes));
        $variant->product_id = $product->id;
        $variant->status = $attributes['status'] ?? ProductVariant::STATUS_ACTIVE;
        $variant->current_stock = $attributes['current_stock'] ?? '0.000';
        $variant->save();

        return $variant;
    }

    private function initialize(ProductVariant $variant, ?string $quantity = null): void
    {
        $quantity ??= (string) $variant->current_stock;

        DB::table('stock_movements')->insert([
            'product_variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_INITIAL_STOCK,
            'quantity_before' => '0.000',
            'quantity_change' => $quantity,
            'quantity_after' => $quantity,
            'performed_by' => $this->actor->id,
            'sale_item_id' => null,
            'restock_item_id' => null,
            'reason' => 'Procurement recommendation test opening count',
            'created_at' => now(),
        ]);
    }

    private function purchaseOrder(string $status, ?PurchaseOrder $parent = null): PurchaseOrder
    {
        $purchaseOrder = new PurchaseOrder([
            'supplier_name' => 'Supplier '.++$this->sequence,
            'notes' => null,
        ]);
        $purchaseOrder->submission_token = Str::uuid()->toString();
        $purchaseOrder->created_by = $this->actor->id;
        $purchaseOrder->status = $status;
        $purchaseOrder->parent_purchase_order_id = $parent?->id;
        $purchaseOrder->save();

        return $purchaseOrder;
    }

    private function purchaseOrderItem(
        PurchaseOrder $purchaseOrder,
        ProductVariant $variant,
        string $orderedQuantity,
    ): PurchaseOrderItem {
        $variant->loadMissing('product:id,name');

        return PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $variant->product->name,
            'size_snapshot' => $variant->size,
            'type_series_snapshot' => $variant->type_series,
            'thickness_snapshot' => $variant->thickness,
            'unit_snapshot' => $variant->unit,
            'ordered_quantity' => $orderedQuantity,
            'expected_unit_cost' => '10.00',
        ]);
    }
}

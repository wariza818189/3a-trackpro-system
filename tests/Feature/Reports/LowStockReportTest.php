<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Sales\PosTestCase;

final class LowStockReportTest extends PosTestCase
{
    public function test_authorization_route_methods_and_reports_entry(): void
    {
        $url = route('reports.low-stock');
        $this->get($url)->assertRedirect('/login');

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get($url)->assertRedirect('/login');
        $this->assertGuest();

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->call('HEAD', $url)->assertOk();
        $this->get(route('reports.index'))->assertOk()
            ->assertSee('Low Stock Report')->assertSee('href="'.$url.'"', false);

        $route = Route::getRoutes()->getByName('reports.low-stock');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('reports/low-stock', $route->uri());
        foreach (['auth', 'active', 'can:access-admin'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $this->{$method}($url)->assertMethodNotAllowed();
        }
    }

    public function test_empty_state_and_eligible_rows_use_exact_stock_states_and_quantity_formats(): void
    {
        $admin = User::factory()->admin()->create();
        $url = route('reports.low-stock');
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('No low-stock items require attention.');

        $product = $this->product($this->category(['name' => 'Fasteners']), ['name' => 'Machine Bolt']);
        $zero = $this->variant($product, [
            'size' => 'Zero Size', 'type_series' => 'Series Zero', 'thickness' => '3 mm',
            'unit' => 'piece', 'current_stock' => '0.000', 'low_stock_threshold' => '0.000',
        ]);
        $low = $this->variant($product, [
            'size' => 'Fractional Size', 'type_series' => 'Series Fractional', 'thickness' => '4 mm',
            'unit' => 'kg', 'quantity_mode' => 'fractional',
            'current_stock' => '0.125', 'low_stock_threshold' => '0.125',
        ]);
        $healthy = $this->variant($product, [
            'size' => 'Healthy Size', 'current_stock' => '2.001', 'low_stock_threshold' => '2.000',
        ]);

        $response = $this->get($url)->assertOk()->assertSee('Fasteners')->assertSee('Machine Bolt');
        $this->assertSame(2, substr_count($response->getContent(), 'data-report-variant='));
        $response->assertSee('data-report-variant="'.$zero->id.'"', false)
            ->assertSeeInOrder(['Zero Size', 'Series Zero', '3 mm', 'piece', '>0<', '>0<', 'Out of stock'], false)
            ->assertSee('data-report-variant="'.$low->id.'"', false)
            ->assertSeeInOrder(['Fractional Size', 'Series Fractional', '4 mm', 'kg', '0.125', '0.125', 'Low stock'])
            ->assertDontSee('data-report-variant="'.$healthy->id.'"', false)
            ->assertDontSee('Healthy Size');
    }

    public function test_inactive_hierarchy_is_excluded_but_uninitialized_active_variant_is_included(): void
    {
        $category = $this->category(['name' => 'Active Category']);
        $product = $this->product($category, ['name' => 'Active Product']);
        $uninitialized = $this->variant($product, [
            'size' => 'Uninitialized', 'current_stock' => '0.000', 'low_stock_threshold' => '1.000',
        ]);
        $this->variant($product, [
            'size' => 'Archived Variant', 'current_stock' => '0.000',
            'status' => ProductVariant::STATUS_ARCHIVED,
        ]);
        $archivedProduct = $this->product($category, [
            'name' => 'Archived Product', 'status' => Product::STATUS_ARCHIVED,
        ]);
        $this->variant($archivedProduct, ['size' => 'Archived Product Variant', 'current_stock' => '0.000']);
        $archivedCategory = $this->category([
            'name' => 'Archived Category', 'status' => Category::STATUS_ARCHIVED,
        ]);
        $this->variant($this->product($archivedCategory), [
            'size' => 'Archived Category Variant', 'current_stock' => '0.000',
        ]);

        $this->assertSame(0, DB::table('stock_movements')->where('product_variant_id', $uninitialized->id)->count());
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('reports.low-stock'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'data-report-variant='));
        $response->assertSee('Uninitialized')->assertSee('Out of stock')
            ->assertDontSee('Archived Variant')->assertDontSee('Archived Product Variant')
            ->assertDontSee('Archived Category Variant');
    }

    public function test_order_is_zero_first_then_category_product_and_variant_identity(): void
    {
        $admin = User::factory()->admin()->create();
        $categoryA = $this->category(['name' => 'A Category']);
        $categoryB = $this->category(['name' => 'B Category']);
        $productA = $this->product($categoryA, ['name' => 'A Product']);
        $productB = $this->product($categoryA, ['name' => 'B Product']);
        $otherProduct = $this->product($categoryB, ['name' => 'A Product']);

        $categoryLast = $this->variant($otherProduct, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'current_stock' => '1.000']);
        $productLast = $this->variant($productB, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'current_stock' => '1.000']);
        $sizeLast = $this->variant($productA, ['size' => 'B', 'type_series' => 'A', 'thickness' => 'A', 'current_stock' => '1.000']);
        $seriesLast = $this->variant($productA, ['size' => 'A', 'type_series' => 'B', 'thickness' => 'A', 'current_stock' => '1.000']);
        $thicknessLast = $this->variant($productA, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'B', 'current_stock' => '1.000']);
        $unitLast = $this->variant($productA, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'unit' => 'roll', 'current_stock' => '1.000']);
        $first = $this->variant($productA, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'unit' => 'piece', 'current_stock' => '1.000']);
        $zero = $this->variant($otherProduct, ['size' => 'Zero', 'current_stock' => '0.000']);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($admin)->get(route('reports.low-stock'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $response->assertSeeInOrder(array_map(
            fn (ProductVariant $variant) => 'data-report-variant="'.$variant->id.'"',
            [$zero, $first, $unitLast, $thicknessLast, $seriesLast, $sizeLast, $productLast, $categoryLast],
        ), false);
        $variantQuery = $queries->first(fn (string $query) => str_contains($query, 'from "product_variants"'));
        $this->assertStringContainsString('"product_variants"."id" asc', $variantQuery);
    }

    public function test_report_is_private_read_only_and_has_bounded_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $variants = collect();
        foreach (range(1, 12) as $index) {
            $variants->push($this->variant($product, [
                'size' => 'Size '.$index, 'current_stock' => '1.000',
                'cost_price' => '8765.43', 'selling_price' => '7654.32',
            ]));
        }
        $before = [
            'categories' => DB::table('categories')->count(),
            'products' => DB::table('products')->count(),
            'product_variants' => DB::table('product_variants')->count(),
            'stock_movements' => DB::table('stock_movements')->count(),
        ];
        $stockBefore = $variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $variant->fresh()->current_stock])->all();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($admin)->get(route('reports.low-stock'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(12, substr_count($response->getContent(), 'data-report-variant='));
        $this->assertLessThanOrEqual(6, $queries->filter(fn (string $query) => str_starts_with(strtolower(ltrim($query)), 'select'))->count());
        $this->assertSame([], $queries->filter(fn (string $query) => preg_match('/\A\s*(insert|update|delete|replace)\b/i', $query) === 1)->all());
        $response->assertDontSee('8765.43')->assertDontSee('7654.32')
            ->assertDontSee('Cost')->assertDontSee('Selling price')
            ->assertDontSee('submission_token')->assertDontSee('checkout_token')
            ->assertDontSee('PO #')->assertDontSee('coverage')
            ->assertDontSee('Edit')->assertDontSee('Archive')->assertDontSee('Receive items');
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame($stockBefore, $variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $variant->fresh()->current_stock])->all());
    }
}

<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Sales\PosTestCase;

final class InventoryReportTest extends PosTestCase
{
    public function test_authorization_route_methods_and_reports_entry(): void
    {
        $url = route('reports.inventory');
        $this->get($url)->assertRedirect('/login');

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get($url)->assertRedirect('/login');
        $this->assertGuest();

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->call('HEAD', $url)->assertOk();
        $this->get(route('reports.index'))->assertOk()
            ->assertSee('Inventory Report')->assertSee('href="'.$url.'"', false);

        $route = Route::getRoutes()->getByName('reports.inventory');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('reports/inventory', $route->uri());
        foreach (['auth', 'active', 'can:access-admin'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $this->{$method}($url)->assertMethodNotAllowed();
        }
    }

    public function test_empty_state_and_all_stock_states_and_quantity_formats(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $url = route('reports.inventory');
        $this->get($url)->assertOk()->assertSee('No inventory records are available.');

        $product = $this->product($this->category(['name' => 'Fasteners']), ['name' => 'Machine Bolt']);
        $zero = $this->variant($product, [
            'size' => 'Zero Size', 'type_series' => 'Series Zero', 'thickness' => '3 mm',
            'unit' => 'piece', 'current_stock' => '0.000', 'low_stock_threshold' => '0.000',
        ]);
        $atThreshold = $this->variant($product, [
            'size' => 'Threshold Size', 'type_series' => 'Series Threshold', 'thickness' => '4 mm',
            'unit' => 'kg', 'quantity_mode' => 'fractional',
            'current_stock' => '0.125', 'low_stock_threshold' => '0.125',
        ]);
        $belowThreshold = $this->variant($product, [
            'size' => 'Below Size', 'current_stock' => '1.000', 'low_stock_threshold' => '2.000',
        ]);
        $inStock = $this->variant($product, [
            'size' => 'In Stock Size', 'current_stock' => '3.000', 'low_stock_threshold' => '2.000',
        ]);

        $html = $this->get($url)->assertOk()->getContent();
        $this->assertSame(4, substr_count($html, 'data-report-variant='));
        $this->assertSame([
            'Fasteners', 'active', 'Machine Bolt', 'active', 'Zero Size', 'Series Zero', '3 mm',
            'piece', 'whole', 'active', '0', '0', 'Out of stock',
        ], $this->rowCells($html, $zero));
        $this->assertSame([
            'Fasteners', 'active', 'Machine Bolt', 'active', 'Threshold Size', 'Series Threshold', '4 mm',
            'kg', 'fractional', 'active', '0.125', '0.125', 'Low stock',
        ], $this->rowCells($html, $atThreshold));
        $this->assertSame('Low stock', $this->rowCells($html, $belowThreshold)[12]);
        $this->assertSame('In stock', $this->rowCells($html, $inStock)[12]);
    }

    public function test_archived_hierarchy_and_uninitialized_variant_are_included(): void
    {
        $category = $this->category(['name' => 'Active Category']);
        $product = $this->product($category, ['name' => 'Active Product']);
        $uninitialized = $this->variant($product, [
            'size' => 'Uninitialized', 'current_stock' => '0.000',
        ]);
        $archivedVariant = $this->variant($product, [
            'size' => 'Archived Variant', 'status' => ProductVariant::STATUS_ARCHIVED,
            'current_stock' => '2.000', 'low_stock_threshold' => '1.000',
        ]);
        $archivedProduct = $this->product($category, [
            'name' => 'Archived Product', 'status' => Product::STATUS_ARCHIVED,
        ]);
        $underArchivedProduct = $this->variant($archivedProduct, ['size' => 'Archived Product Variant']);
        $archivedCategory = $this->category([
            'name' => 'Archived Category', 'status' => Category::STATUS_ARCHIVED,
        ]);
        $underArchivedCategory = $this->variant($this->product($archivedCategory), [
            'size' => 'Archived Category Variant',
        ]);

        $this->assertSame(0, DB::table('stock_movements')->where('product_variant_id', $uninitialized->id)->count());
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('reports.inventory'))->assertOk()->getContent();
        $this->assertSame(4, substr_count($html, 'data-report-variant='));
        $this->assertSame('Out of stock', $this->rowCells($html, $uninitialized)[12]);
        $this->assertSame('archived', $this->rowCells($html, $archivedVariant)[9]);
        $this->assertSame('In stock', $this->rowCells($html, $archivedVariant)[12]);
        $this->assertSame('archived', $this->rowCells($html, $underArchivedProduct)[3]);
        $this->assertSame('archived', $this->rowCells($html, $underArchivedCategory)[1]);
    }

    public function test_order_is_category_product_variant_identity_then_id(): void
    {
        $categoryA = $this->category(['name' => 'A Category']);
        $categoryB = $this->category(['name' => 'B Category']);
        $productA = $this->product($categoryA, ['name' => 'A Product']);
        $productB = $this->product($categoryA, ['name' => 'B Product']);
        $otherProduct = $this->product($categoryB, ['name' => 'A Product']);

        $categoryLast = $this->variant($otherProduct, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'current_stock' => '0.000']);
        $productLast = $this->variant($productB, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A']);
        $sizeLast = $this->variant($productA, ['size' => 'B', 'type_series' => 'A', 'thickness' => 'A']);
        $seriesLast = $this->variant($productA, ['size' => 'A', 'type_series' => 'B', 'thickness' => 'A']);
        $thicknessLast = $this->variant($productA, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'B']);
        $unitLast = $this->variant($productA, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'unit' => 'roll']);
        $first = $this->variant($productA, ['size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'unit' => 'piece']);
        $idTie = $this->variant($productA, [
            'size' => 'A', 'type_series' => 'A', 'thickness' => 'A', 'unit' => 'piece',
            'quantity_mode' => 'fractional',
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('reports.inventory'))->assertOk()->getContent();
        preg_match_all('/data-report-variant="(\d+)"/', $html, $matches);
        $this->assertSame(array_map(
            fn (ProductVariant $variant) => (string) $variant->id,
            [$first, $idTie, $unitLast, $thicknessLast, $seriesLast, $sizeLast, $productLast, $categoryLast],
        ), $matches[1]);
    }

    public function test_report_has_no_private_data_totals_or_writes_and_has_bounded_queries(): void
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
        $response = $this->actingAs($admin)->get(route('reports.inventory'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(12, substr_count($response->getContent(), 'data-report-variant='));
        $this->assertLessThanOrEqual(6, $queries->filter(fn (string $query) => str_starts_with(strtolower(ltrim($query)), 'select'))->count());
        $this->assertSame([], $queries->filter(fn (string $query) => preg_match('/\A\s*(insert|update|delete|replace)\b/i', $query) === 1)->all());
        $response->assertDontSee('8765.43')->assertDontSee('7654.32')
            ->assertDontSee('Cost')->assertDontSee('Selling price')
            ->assertDontSee('submission_token')->assertDontSee('checkout_token')
            ->assertDontSee('PO #')->assertDontSee('coverage')
            ->assertDontSee('Edit')->assertDontSee('Receive items')
            ->assertDontSee('Total quantity')->assertDontSee('<tfoot', false);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame($stockBefore, $variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $variant->fresh()->current_stock])->all());
    }

    /** @return list<string> */
    private function rowCells(string $html, ProductVariant $variant): array
    {
        $this->assertSame(1, preg_match('/<tr data-report-variant="'.$variant->id.'">(.*?)<\/tr>/s', $html, $row));
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row[1], $cells);

        return array_map(fn (string $cell): string => trim(strip_tags($cell)), $cells[1]);
    }
}

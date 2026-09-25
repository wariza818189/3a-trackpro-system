<?php

namespace Tests\Feature\Reports;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Sales\PosTestCase;

class ProductSalesReportTest extends PosTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_route_authorization_and_reports_index_link(): void
    {
        $route = Route::getRoutes()->getByName('reports.product-sales');
        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertContains('can:access-admin', $route->gatherMiddleware());
        $this->assertNull(Route::getRoutes()->getByName('reports.product-sales.store'));

        $url = route('reports.product-sales');
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->disabled()->create())->get($url)->assertRedirect('/login');
        $this->assertGuest();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get($url)->assertOk();
        $this->actingAs($admin)->head($url)->assertOk();
        $this->actingAs($admin)->post($url)->assertMethodNotAllowed();
        $this->actingAs($admin)->put($url)->assertMethodNotAllowed();
        $this->actingAs($admin)->patch($url)->assertMethodNotAllowed();
        $this->actingAs($admin)->delete($url)->assertMethodNotAllowed();
        $this->actingAs($admin)->get(route('reports.index'))
            ->assertOk()->assertSee($url, false)->assertSee('Product Sales Report');
    }

    public function test_default_window_is_seven_manila_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category(), ['name' => 'Window Product']));
        $this->saleItem($admin, $variant, '2026-09-02 00:00:00', '1.000', '10.10');
        $this->saleItem($admin, $variant, '2026-09-08 23:59:59', '1.000', '20.20');
        $this->saleItem($admin, $variant, '2026-09-01 23:59:59', '1.000', '300.00');
        $this->saleItem($admin, $variant, '2026-09-09 00:00:00', '1.000', '400.00');

        $response = $this->actingAs($admin)->get(route('reports.product-sales'))->assertOk()
            ->assertSee('name="date_from" value="2026-09-02"', false)
            ->assertSee('name="date_to" value="2026-09-08"', false)
            ->assertSee('Window Product')->assertSee('2.000')->assertSee('₱30.30')
            ->assertDontSee('₱300.00')->assertDontSee('₱400.00');
        $this->assertCount(1, $response->viewData('rows'));
    }

    public function test_explicit_range_is_inclusive_and_only_completed_sales_qualify(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->saleItem($admin, $variant, '2026-09-07 23:59:59', '1.000', '100.00');
        $this->saleItem($admin, $variant, '2026-09-08 00:00:00', '1.000', '10.10');
        $this->saleItem($admin, $variant, '2026-09-08 23:59:59', '1.000', '20.20');
        $this->saleItem($admin, $variant, '2026-09-09 00:00:00', '1.000', '200.00');
        $this->saleItem($admin, $variant, '2026-09-08 12:00:00', '9.000', '999.99', Sale::STATUS_VOIDED);

        $rows = $this->actingAs($admin)->get($this->url())->assertOk()
            ->assertSee('₱30.30')->assertDontSee('₱999.99')->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame('2.000', $rows[0]['quantity']);
        $this->assertSame('30.30', $rows[0]['sales_amount']);
    }

    public function test_invalid_dates_fail_closed_without_running_sale_item_query(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->saleItem($admin, $variant, '2026-09-08 12:00:00', '1.000', '100.00');

        foreach ([
            ['date_from' => 'bad', 'date_to' => '2026-09-08'],
            ['date_from' => '2026-09-08'],
            ['date_to' => '2026-09-08'],
            ['date_from' => '2026-09-09', 'date_to' => '2026-09-08'],
            ['date_from' => '2025-09-08', 'date_to' => '2026-09-09'],
        ] as $query) {
            $queries = [];
            DB::listen(static function ($event) use (&$queries): void {
                $queries[] = $event->sql;
            });
            $response = $this->actingAs($admin)->get(route('reports.product-sales', $query))
                ->assertOk()->assertSee('The report was not run')
                ->assertDontSee('₱100.00')
                ->assertDontSee('No completed product sales were recorded');
            $this->assertCount(0, $response->viewData('rows'));
            $this->assertEmpty(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'sale_items')));
        }
    }

    public function test_complete_historical_identity_groups_exact_decimal_values(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category(), ['name' => 'Old Product']);
        $first = $this->variant($product, ['size' => 'A', 'type_series' => 'Series', 'thickness' => 'Thin', 'unit' => 'm', 'quantity_mode' => 'fractional']);
        $second = $this->variant($product, ['size' => 'A', 'type_series' => 'Series', 'thickness' => 'Thin', 'unit' => 'm', 'quantity_mode' => 'fractional']);
        $third = $this->variant($product, ['size' => 'A', 'type_series' => 'Series', 'thickness' => 'Thin', 'unit' => 'piece']);
        $this->saleItem($admin, $first, '2026-09-08 09:00:00', '0.125', '0.01', snapshots: ['product_name_snapshot' => 'Old Product']);
        $this->saleItem($admin, $first, '2026-09-08 10:00:00', '0.375', '0.02', snapshots: ['product_name_snapshot' => 'Old Product']);
        $this->saleItem($admin, $first, '2026-09-08 11:00:00', '1.000', '1.00', snapshots: ['product_name_snapshot' => 'New Product']);
        $this->saleItem($admin, $second, '2026-09-08 12:00:00', '2.000', '2.00');
        $this->saleItem($admin, $third, '2026-09-08 13:00:00', '3.000', '3.00');
        DB::table('products')->where('id', $product->id)->update(['name' => 'Current Catalog Name', 'status' => 'archived']);
        DB::table('product_variants')->where('id', $first->id)->update(['size' => 'Changed Size', 'unit' => 'kg', 'status' => 'archived']);

        $response = $this->actingAs($admin)->get($this->url())->assertOk()
            ->assertSee('Old Product')->assertSee('New Product')->assertSee('A · Series · Thin')
            ->assertSee('0.500')->assertSee('₱0.03')->assertDontSee('Current Catalog Name')
            ->assertDontSee('Changed Size')->assertDontSee('kg');
        $rows = $response->viewData('rows')->all();
        $this->assertCount(4, $rows);
        $this->assertSame([$first->id, $first->id, $second->id, $third->id], array_column($rows, 'product_variant_id'));
        $this->assertSame(['1.000', '0.500', '2.000', '3.000'], array_column($rows, 'quantity'));
        $this->assertSame(['1.00', '0.03', '2.00', '3.00'], array_column($rows, 'sales_amount'));
        $this->assertSame(['m', 'm', 'm', 'piece'], array_column($rows, 'unit_snapshot'));
    }

    public function test_ordering_follows_each_snapshot_field_then_variant_id(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $variants = [];
        foreach (range(0, 6) as $index) {
            $variants[] = $this->variant($product);
        }
        $identities = [
            ['B', 'A', 'A', 'A', 'a'],
            ['A', 'B', 'A', 'A', 'a'],
            ['A', 'A', 'B', 'A', 'a'],
            ['A', 'A', 'A', 'B', 'a'],
            ['A', 'A', 'A', 'A', 'b'],
            ['A', 'A', 'A', 'A', 'a'],
            ['A', 'A', 'A', 'A', 'a'],
        ];
        foreach ($variants as $index => $variant) {
            [$name, $size, $type, $thickness, $unit] = $identities[$index];
            $this->saleItem($admin, $variant, '2026-09-08 12:00:00', '1.000', '1.00', snapshots: [
                'product_name_snapshot' => $name, 'size_snapshot' => $size,
                'type_series_snapshot' => $type, 'thickness_snapshot' => $thickness,
                'unit_snapshot' => $unit,
            ]);
        }
        $ids = array_column($this->actingAs($admin)->get($this->url())->assertOk()->viewData('rows')->all(), 'product_variant_id');
        $this->assertSame([$variants[5]->id, $variants[6]->id, $variants[4]->id, $variants[3]->id, $variants[2]->id, $variants[1]->id, $variants[0]->id], $ids);
    }

    public function test_reconciles_with_sales_summary_and_uses_stored_line_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->saleItem($admin, $variant, '2026-09-08 10:00:00', '0.333', '0.01', unitPrice: '0.04');
        $this->saleItem($admin, $variant, '2026-09-08 11:00:00', '0.333', '0.02', unitPrice: '0.07');
        $this->saleItem($admin, $variant, '2026-09-08 12:00:00', '1.000', '4.50');

        $rows = $this->actingAs($admin)->get($this->url())->assertOk()->viewData('rows');
        $total = $rows->reduce(static fn (string $sum, array $row): string => bcadd($sum, $row['sales_amount'], 2), '0.00');
        $summary = $this->actingAs($admin)->get(route('reports.index', ['date_from' => '2026-09-08', 'date_to' => '2026-09-08']))->assertOk();
        $this->assertSame('1.666', $rows[0]['quantity']);
        $this->assertSame('4.53', $total);
        $this->assertSame($summary->viewData('salesTotal'), $total);
    }

    public function test_empty_privacy_read_only_and_bounded_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['cost_price' => '8765.43']);
        $this->actingAs($admin)->get($this->url())->assertOk()->assertSee('No completed product sales were recorded');
        foreach (range(1, 12) as $index) {
            $this->saleItem($admin, $variant, "2026-09-08 12:00:$index", '1.000', '2.00', snapshots: ['product_name_snapshot' => 'Product '.$index]);
        }
        $before = collect(['sales', 'sale_items', 'products', 'product_variants', 'stock_movements', 'audit_logs'])
            ->mapWithKeys(static fn (string $table): array => [$table => DB::table($table)->count()])->all();
        $queries = [];
        DB::listen(static function ($event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        $response = $this->actingAs($admin)->get($this->url())->assertOk();
        $this->assertCount(12, $response->viewData('rows'));
        $this->assertCount(1, array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'sale_items')));
        $this->assertLessThanOrEqual(5, count($queries));
        foreach (['8765.43', 'Profit', 'Margin', 'Cash received', 'Change', 'submission_token', 'checkout_token', 'Unit price', 'Delete', 'Edit'] as $private) {
            $response->assertDontSee($private);
        }
        $after = collect(array_keys($before))->mapWithKeys(static fn (string $table): array => [$table => DB::table($table)->count()])->all();
        $this->assertSame($before, $after);
    }

    private function url(): string
    {
        return route('reports.product-sales', ['date_from' => '2026-09-08', 'date_to' => '2026-09-08']);
    }

    private function saleItem(
        User $cashier,
        ProductVariant $variant,
        string $createdAt,
        string $quantity,
        string $lineTotal,
        string $status = Sale::STATUS_COMPLETED,
        array $snapshots = [],
        ?string $unitPrice = null,
    ): void {
        $saleId = DB::table('sales')->insertGetId([
            'checkout_token' => Str::uuid()->toString(), 'recorded_by' => $cashier->id,
            'status' => $status, 'total_amount' => $lineTotal,
            'cash_received' => $lineTotal, 'change_amount' => '0.00', 'created_at' => $createdAt,
        ]);
        DB::table('sale_items')->insert(array_replace([
            'sale_id' => $saleId, 'product_variant_id' => $variant->id,
            'product_name_snapshot' => $variant->product->name,
            'size_snapshot' => $variant->size, 'type_series_snapshot' => $variant->type_series,
            'thickness_snapshot' => $variant->thickness, 'unit_snapshot' => $variant->unit,
            'quantity' => $quantity, 'unit_price' => $unitPrice ?? $lineTotal,
            'line_total' => $lineTotal, 'created_at' => $createdAt,
        ], $snapshots));
    }
}

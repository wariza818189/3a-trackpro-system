<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Sales\PosTestCase;

class DashboardTest extends PosTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_empty_dashboard_has_exact_zero_values_and_controlled_empty_lists(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(["Today's Sales", '₱0.00', 'Transactions Today', '0'])
            ->assertSee('No completed Sales yet.')
            ->assertSee('No low-stock active items.');
    }

    public function test_today_uses_half_open_manila_boundaries_and_completed_sales_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));

        $this->sale($admin, $variant, '100.10', '2026-09-07 23:59:59');
        $start = $this->sale($admin, $variant, '10.10', '2026-09-08 00:00:00');
        $late = $this->sale($admin, $variant, '20.20', '2026-09-08 23:59:59');
        $this->sale($admin, $variant, '200.20', '2026-09-09 00:00:00');
        $voided = $this->sale($admin, $variant, '9999.99', '2026-09-08 13:00:00', Sale::STATUS_VOIDED, 'private-voided-token');

        $response = $this->actingAs($admin)->get(route('home'))->assertOk();
        $response->assertSeeInOrder(["Today's Sales", '₱30.30', 'Transactions Today', '2'])
            ->assertSee($start->receiptNumber())
            ->assertSee($late->receiptNumber())
            ->assertDontSee($voided->receiptNumber())
            ->assertDontSee('9999.99')
            ->assertDontSee('private-voided-token');
    }

    public function test_low_stock_and_out_of_stock_use_only_the_full_active_hierarchy(): void
    {
        $staff = User::factory()->create();
        $activeCategory = $this->category(['name' => 'Active Category']);
        $activeProduct = $this->product($activeCategory, ['name' => 'Active Product']);
        $out = $this->variant($activeProduct, [
            'size' => 'Out Zero', 'current_stock' => '0.000', 'low_stock_threshold' => '0.000',
        ]);
        $low = $this->variant($activeProduct, [
            'size' => 'Low Equal', 'current_stock' => '2.000', 'low_stock_threshold' => '2.000',
        ]);
        $this->variant($activeProduct, [
            'size' => 'Healthy', 'current_stock' => '2.001', 'low_stock_threshold' => '2.000',
        ]);
        $this->variant($activeProduct, [
            'size' => 'Archived Variant', 'current_stock' => '0.000', 'low_stock_threshold' => '5.000',
            'status' => ProductVariant::STATUS_ARCHIVED,
        ]);
        $archivedProduct = $this->product($activeCategory, [
            'name' => 'Archived Product', 'status' => Product::STATUS_ARCHIVED,
        ]);
        $this->variant($archivedProduct, ['size' => 'Hidden Product Variant', 'current_stock' => '0.000']);
        $archivedCategory = $this->category([
            'name' => 'Archived Category', 'status' => Category::STATUS_ARCHIVED,
        ]);
        $this->variant($this->product($archivedCategory, ['name' => 'Hidden Category Product']), [
            'size' => 'Hidden Category Variant', 'current_stock' => '0.000',
        ]);

        $response = $this->actingAs($staff)->get(route('home'))->assertOk();
        $response->assertSeeInOrder(['Low Stock', '2', 'Out of Stock', '1'])
            ->assertSeeInOrder([$out->size, $low->size])
            ->assertSee('0.000 / 0.000')
            ->assertDontSee('Healthy')
            ->assertDontSee('Archived Variant')
            ->assertDontSee('Hidden Product Variant')
            ->assertDontSee('Hidden Category Variant');
    }

    public function test_recent_completed_sales_are_limited_and_deterministically_ordered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'Asia/Manila'));
        $cashier = User::factory()->create(['name' => 'Recent Cashier']);
        $viewer = User::factory()->create();
        $variant = $this->variant($this->product($this->category()), ['cost_price' => '8765.43']);
        $sales = collect();
        for ($index = 0; $index < 6; $index++) {
            $sales->push($this->sale(
                $cashier,
                $variant,
                (string) (100 + $index).'.00',
                '2026-09-08 10:00:00',
                Sale::STATUS_COMPLETED,
                $index === 5 ? 'private-checkout-token' : null,
            ));
        }

        $response = $this->actingAs($viewer)->get(route('home'))->assertOk();
        $response->assertSeeInOrder($sales->reverse()->take(5)->map->receiptNumber()->all())
            ->assertDontSee($sales->first()->receiptNumber())
            ->assertSee('Recent Cashier')
            ->assertSee('completed')
            ->assertSee('View receipt')
            ->assertDontSee('8765.43')
            ->assertDontSee('private-checkout-token')
            ->assertDontSee('StockMovement');
    }

    public function test_admin_trend_contains_all_seven_days_and_excludes_non_completed_sales(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->sale($admin, $variant, '15.25', '2026-09-02 08:00:00');
        $this->sale($admin, $variant, '24.75', '2026-09-08 09:00:00');
        $this->sale($admin, $variant, '7777.77', '2026-09-03 09:00:00', Sale::STATUS_VOIDED);

        $response = $this->actingAs($admin)->get(route('home'))->assertOk();
        $response->assertSeeInOrder(['Sep 2', 'Sep 3', 'Sep 4', 'Sep 5', 'Sep 6', 'Sep 7', 'Sep 8'])
            ->assertSee('₱15.25')
            ->assertSee('₱24.75')
            ->assertSee('₱0.00')
            ->assertDontSee('7777.77');
    }

    public function test_dashboard_gets_are_read_only(): void
    {
        $staff = User::factory()->create();
        $variant = $this->variant($this->product($this->category()));
        $sale = $this->sale($staff, $variant, '50.00', now()->format('Y-m-d H:i:s'));
        $before = $this->domainCounts();

        $this->actingAs($staff)->get(route('home'))->assertOk()->assertSee($sale->receiptNumber());
        $this->actingAs($staff)->get(route('home'))->assertOk()->assertSee($sale->receiptNumber());

        $this->assertSame($before, $this->domainCounts());
    }

    private function sale(
        User $cashier,
        ProductVariant $variant,
        string $total,
        string $createdAt,
        string $status = Sale::STATUS_COMPLETED,
        ?string $token = null,
    ): Sale {
        $saleId = DB::table('sales')->insertGetId([
            'checkout_token' => $token ?? Str::uuid()->toString(),
            'recorded_by' => $cashier->id,
            'status' => $status,
            'total_amount' => $total,
            'cash_received' => $total,
            'change_amount' => '0.00',
            'created_at' => $createdAt,
        ]);
        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $variant->product->name,
            'size_snapshot' => $variant->size,
            'type_series_snapshot' => $variant->type_series,
            'thickness_snapshot' => $variant->thickness,
            'unit_snapshot' => $variant->unit,
            'quantity' => '1.000',
            'unit_price' => $total,
            'line_total' => $total,
            'created_at' => $createdAt,
        ]);

        return Sale::query()->findOrFail($saleId);
    }

    /** @return array<string, int> */
    private function domainCounts(): array
    {
        return collect(['users', 'categories', 'products', 'product_variants', 'sales', 'sale_items', 'stock_movements', 'audit_logs'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}

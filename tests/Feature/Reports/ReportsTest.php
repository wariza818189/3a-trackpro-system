<?php

namespace Tests\Feature\Reports;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Sales\PosTestCase;

class ReportsTest extends PosTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_report_uses_the_latest_seven_manila_calendar_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->sale($admin, $variant, '25.10', '1.000', 'piece', '2026-09-02 00:00:00');
        $this->sale($admin, $variant, '14.90', '1.000', 'piece', '2026-09-08 23:59:59');
        $this->sale($admin, $variant, '500.00', '1.000', 'piece', '2026-09-01 23:59:59');
        $this->sale($admin, $variant, '600.00', '1.000', 'piece', '2026-09-09 00:00:00');

        $response = $this->actingAs($admin)->get(route('reports.index'))->assertOk();
        $response->assertSee('name="date_from" value="2026-09-02"', false)
            ->assertSee('name="date_to" value="2026-09-08"', false)
            ->assertSeeInOrder(['Completed Sales Total', '₱40.00', 'Completed Transactions', '2'])
            ->assertSeeInOrder(['Sep 8, 2026', 'Sep 7, 2026', 'Sep 6, 2026', 'Sep 5, 2026', 'Sep 4, 2026', 'Sep 3, 2026', 'Sep 2, 2026'])
            ->assertDontSee('₱500.00')
            ->assertDontSee('₱600.00');
    }

    public function test_explicit_range_uses_half_open_boundaries_and_excludes_non_completed_sales(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->sale($admin, $variant, '100.00', '1.000', 'piece', '2026-09-07 23:59:59');
        $this->sale($admin, $variant, '10.10', '1.000', 'piece', '2026-09-08 00:00:00');
        $this->sale($admin, $variant, '20.20', '1.000', 'piece', '2026-09-08 23:59:59');
        $this->sale($admin, $variant, '200.00', '1.000', 'piece', '2026-09-09 00:00:00');
        $this->sale($admin, $variant, '9999.99', '9.000', 'piece', '2026-09-08 12:00:00', Sale::STATUS_VOIDED, 'private-void-token');

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'date_from' => '2026-09-08',
            'date_to' => '2026-09-08',
        ]))->assertOk();

        $response->assertSeeInOrder(['Completed Sales Total', '₱30.30', 'Completed Transactions', '2'])
            ->assertSeeInOrder(['piece', '2.000'])
            ->assertDontSee('9999.99')
            ->assertDontSee('9.000')
            ->assertDontSee('private-void-token');
    }

    public function test_invalid_date_filters_fail_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));
        $this->sale($admin, $variant, '100.00', '1.000', 'piece', '2026-09-08 10:00:00');
        $invalidQueries = [
            ['date_from' => '2026-09-08'],
            ['date_to' => '2026-09-08'],
            ['date_from' => '', 'date_to' => '2026-09-08'],
            ['date_from' => 'bad', 'date_to' => '2026-09-08'],
            ['date_from' => '2026-02-31', 'date_to' => '2026-03-01'],
            ['date_from' => ['2026-09-08'], 'date_to' => '2026-09-08'],
            ['date_from' => '2026-09-09', 'date_to' => '2026-09-08'],
            ['date_from' => '2025-09-08', 'date_to' => '2026-09-09'],
        ];

        foreach ($invalidQueries as $query) {
            $this->actingAs($admin)->get(route('reports.index', $query))
                ->assertOk()
                ->assertSee('The report was not run')
                ->assertSeeInOrder(['Completed Sales Total', '₱0.00', 'Completed Transactions', '0'])
                ->assertDontSee('₱100.00');
        }
    }

    public function test_cashier_filter_is_narrow_and_keeps_disabled_historical_cashiers(): void
    {
        $admin = User::factory()->admin()->create();
        $disabledCashier = User::factory()->create([
            'name' => 'Disabled Historical Cashier', 'username' => 'private_disabled_username',
        ]);
        $otherCashier = User::factory()->create(['name' => 'Other Cashier']);
        $variant = $this->variant($this->product($this->category()));
        $this->sale($disabledCashier, $variant, '40.00', '1.000', 'piece', '2026-09-08 10:00:00');
        $this->sale($otherCashier, $variant, '60.00', '1.000', 'piece', '2026-09-08 11:00:00');
        $disabledCashier->status = 'disabled';
        $disabledCashier->save();

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'date_from' => '2026-09-08', 'date_to' => '2026-09-08', 'cashier' => $disabledCashier->id,
        ]))->assertOk();
        $response->assertSee('Disabled Historical Cashier')
            ->assertSeeInOrder(['Completed Sales Total', '₱40.00', 'Completed Transactions', '1'])
            ->assertDontSee('private_disabled_username');

        foreach ([['cashier' => 'invalid'], ['cashier' => ['1']], ['cashier' => '0']] as $query) {
            $this->actingAs($admin)->get(route('reports.index', $query))
                ->assertOk()->assertSee('Select a valid cashier.')->assertSee('The report was not run');
        }
    }

    public function test_quantities_remain_separate_and_use_historical_unit_snapshots(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category(), ['name' => 'Historical Product']);
        $piece = $this->variant($product, ['size' => 'Piece', 'unit' => 'piece']);
        $metre = $this->variant($product, ['size' => 'Metre', 'unit' => 'm', 'quantity_mode' => 'fractional']);
        $this->sale($admin, $piece, '20.00', '2.000', 'piece', '2026-09-08 09:00:00');
        $this->sale($admin, $metre, '30.00', '1.250', 'm', '2026-09-08 10:00:00');

        DB::table('product_variants')->whereKey($piece->id)->update(['unit' => 'kg', 'status' => ProductVariant::STATUS_ARCHIVED]);
        DB::table('products')->whereKey($product->id)->update(['name' => 'Current Renamed Product']);

        $this->actingAs($admin)->get(route('reports.index', [
            'date_from' => '2026-09-08', 'date_to' => '2026-09-08',
        ]))->assertOk()
            ->assertSeeInOrder(['piece', '2.000', 'm', '1.250'])
            ->assertDontSee('kg')
            ->assertDontSee('Current Renamed Product');
    }

    public function test_report_is_private_and_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['cost_price' => '8765.43']);
        $sale = $this->sale($admin, $variant, '50.00', '1.000', 'piece', now()->format('Y-m-d H:i:s'), Sale::STATUS_COMPLETED, 'private-checkout-token');
        $before = $this->domainCounts();

        foreach (range(1, 2) as $_) {
            $this->actingAs($admin)->get(route('reports.index'))
                ->assertOk()
                ->assertSee('₱'.$sale->total_amount)
                ->assertDontSee('8765.43')
                ->assertDontSee('private-checkout-token')
                ->assertDontSee('StockMovement')
                ->assertDontSee('Profit')
                ->assertDontSee($admin->username);
        }

        $this->assertSame($before, $this->domainCounts());
    }

    private function sale(
        User $cashier,
        ProductVariant $variant,
        string $total,
        string $quantity,
        string $unit,
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
            'unit_snapshot' => $unit,
            'quantity' => $quantity,
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

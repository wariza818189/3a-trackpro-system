<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\CashRegisterSession;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CashRegister\CloseCashRegister;
use App\Services\Sales\RecordSale;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SaleVoidHttpTest extends PosTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_voids_sale_from_closed_session_through_service_backed_endpoint(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Void Administrator']);
        $session = $this->openCashRegister($admin, '125.00');
        $variant = $this->initializedVariant($admin, ['current_stock' => '10.000']);
        $sale = $this->recordSale($admin, $variant, '2.000', '300.00');
        app(CloseCashRegister::class)->execute($admin);

        $saleEvidence = $sale->only([
            'recorded_by', 'cash_register_session_id', 'total_amount', 'cash_received', 'change_amount', 'created_at',
        ]);
        $itemIds = $sale->items()->orderBy('id')->pluck('id')->all();
        $sessionEvidence = $session->fresh()->getAttributes();

        $response = $this->actingAs($admin)->from(route('sales.show', $sale))->patch(route('sales.void', $sale), [
            'reason' => "  Customer   cancelled\nfull order  ",
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('sales.show', $sale))
            ->assertSessionHas('success', 'Sale voided successfully.');
        $fresh = $sale->fresh();
        $this->assertSame(Sale::STATUS_VOIDED, $fresh->status);
        $this->assertSame('Customer cancelled full order', $fresh->void_reason);
        $this->assertSame($admin->id, $fresh->voided_by);
        $this->assertNotNull($fresh->voided_at);
        foreach ($saleEvidence as $field => $value) {
            $this->assertSame((string) $value, (string) $fresh->{$field});
        }
        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame($itemIds, $fresh->items()->orderBy('id')->pluck('id')->all());
        $this->assertSame(1, $this->voidMovements($sale)->count());
        $this->assertSame(1, $this->voidAudits($sale)->count());
        $this->assertSame($sessionEvidence, $session->fresh()->getAttributes());
        $this->assertSame(1, CashRegisterSession::query()->count());
    }

    public function test_void_endpoint_denies_staff_guest_and_disabled_admin_without_mutation(): void
    {
        $recorder = User::factory()->admin()->create();
        $this->openCashRegister($recorder);
        $variant = $this->initializedVariant($recorder);
        $sale = $this->recordSale($recorder, $variant);
        $url = route('sales.void', $sale);

        $this->actingAs(User::factory()->create())->patch($url, ['reason' => 'Staff attempt'])->assertForbidden();
        $this->assertUnchanged($sale, $variant, '9.000');

        $this->app['auth']->forgetGuards();
        $this->patch($url, ['reason' => 'Guest attempt'])->assertRedirect('/login');
        $this->assertUnchanged($sale, $variant, '9.000');

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->patch($url, ['reason' => 'Disabled attempt'])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertUnchanged($sale, $variant, '9.000');
    }

    public function test_request_rejects_invalid_reason_and_every_unexpected_authoritative_field(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, $variant);

        foreach ([[], ['reason' => " \t\n "], ['reason' => str_repeat('é', 1001)]] as $payload) {
            $this->actingAs($admin)->from(route('sales.show', $sale))->patch(route('sales.void', $sale), $payload)
                ->assertRedirect(route('sales.show', $sale))
                ->assertSessionHasErrors('reason');
        }

        $this->actingAs($admin)->from(route('sales.show', $sale))->patch(route('sales.void', $sale), [
            'reason' => 'Valid reason',
            'status' => Sale::STATUS_VOIDED,
            'voided_by' => $admin->id,
            'quantity' => '999.000',
            'refund_amount' => '100.00',
        ])->assertRedirect(route('sales.show', $sale))->assertSessionHasErrors('request');

        $this->assertUnchanged($sale, $variant, '9.000');
    }

    public function test_duplicate_http_submission_is_controlled_and_restores_once(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, $variant, '2.000');
        $url = route('sales.void', $sale);

        $this->actingAs($admin)->patch($url, ['reason' => 'First submission'])
            ->assertRedirect(route('sales.show', $sale));
        $this->from(route('sales.show', $sale))->patch($url, ['reason' => 'Replayed submission'])
            ->assertRedirect(route('sales.show', $sale))
            ->assertSessionHasErrors('sale');

        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame(1, $this->voidMovements($sale)->count());
        $this->assertSame(1, $this->voidAudits($sale)->count());
    }

    public function test_receipt_shows_admin_form_then_safe_void_metadata_and_preserves_original_evidence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-26 2:30 PM', config('app.timezone')));
        $admin = User::factory()->admin()->create(['name' => 'Metadata Admin']);
        $staff = User::factory()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin, [
            'selling_price' => '123.45', 'current_stock' => '10.000',
        ], ['name' => 'Historical Hammer']);
        $sale = $this->recordSale($admin, $variant, '2.000', '300.00');

        $before = $this->domainState($sale, $variant);
        $adminView = $this->actingAs($admin)->get(route('sales.show', $sale))->assertOk()
            ->assertSee('Void Sale')
            ->assertSee('name="reason"', false)
            ->assertSee('entire Sale')
            ->assertSee('restore all sold stock')
            ->assertSee('onsubmit="return confirm(', false)
            ->assertDontSee('name="sale_item_id"', false)
            ->assertDontSee('name="quantity"', false)
            ->assertDontSee('checkout_token');
        $this->actingAs($staff)->get(route('sales.show', $sale))->assertOk()
            ->assertDontSee('action="'.route('sales.void', $sale).'"', false)
            ->assertDontSee('name="reason"', false);
        $this->assertSame($before, $this->domainState($sale, $variant));

        $reason = '<script>alert("private reason")</script>';
        $this->actingAs($admin)->patch(route('sales.void', $sale), ['reason' => $reason]);
        $response = $this->get(route('sales.show', $sale))->assertOk()
            ->assertSee('Sale voided successfully.')
            ->assertSee(['Voided', 'Void reason', 'Voided by', 'Metadata Admin', 'Voided at'])
            ->assertSee($reason)
            ->assertDontSee($reason, false)
            ->assertSee('Sep 26, 2026 2:30 PM')
            ->assertSee('Historical Hammer')
            ->assertSee('2.000')
            ->assertSee('₱123.45')
            ->assertSee('₱246.90')
            ->assertSee('₱300.00')
            ->assertSee('₱53.10')
            ->assertSee('Print receipt')
            ->assertDontSee('action="'.route('sales.void', $sale).'"', false)
            ->assertDontSee('name="reason"', false)
            ->assertDontSee($sale->checkout_token)
            ->assertDontSee('password');
        $this->assertStringContainsString('print:hidden', $response->getContent());
        $this->assertSame(1, Sale::query()->whereKey($sale->id)->count());
        $this->assertSame(1, SaleItem::query()->where('sale_id', $sale->id)->count());
    }

    public function test_sales_history_retains_completed_and_voided_sales_with_accurate_wording(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $firstVariant = $this->initializedVariant($admin, ['size' => 'Voided line']);
        $secondVariant = $this->initializedVariant($admin, ['size' => 'Completed line']);
        $voided = $this->recordSale($admin, $firstVariant);
        $completed = $this->recordSale($admin, $secondVariant);
        $this->actingAs($admin)->patch(route('sales.void', $voided), ['reason' => 'History test']);

        $this->get(route('sales.index'))->assertOk()
            ->assertSee('Sales transactions')
            ->assertDontSee('Completed transactions')
            ->assertSee([$voided->receiptNumber(), $completed->receiptNumber(), 'Voided', 'Completed']);
    }

    public function test_voided_sale_is_excluded_from_summary_product_sales_and_dashboard_outputs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin, ['selling_price' => '25.00']);
        $sale = $this->recordSale($admin, $variant, '2.000', '60.00');
        $range = ['date_from' => '2026-09-26', 'date_to' => '2026-09-26'];

        $summaryBefore = $this->actingAs($admin)->get(route('reports.index', $range))->assertOk();
        $this->assertSame('50.00', $summaryBefore->viewData('salesTotal'));
        $this->assertSame(1, $summaryBefore->viewData('transactionCount'));
        $this->assertSame('2.000', $summaryBefore->viewData('quantitiesByUnit')->sole()['quantity_total']);
        $productBefore = $this->get(route('reports.product-sales', $range))->assertOk()->viewData('rows');
        $this->assertSame('2.000', $productBefore->sole()['quantity']);
        $this->assertSame('50.00', $productBefore->sole()['sales_amount']);
        $dashboardBefore = $this->get(route('home'))->assertOk();
        $this->assertSame('50.00', $dashboardBefore->viewData('todaySalesTotal'));
        $this->assertSame(1, $dashboardBefore->viewData('todayTransactionCount'));
        $this->assertTrue($dashboardBefore->viewData('recentSales')->contains('id', $sale->id));

        $this->patch(route('sales.void', $sale), ['reason' => 'Reporting reversal'])->assertSessionHasNoErrors();

        $summaryAfter = $this->get(route('reports.index', $range))->assertOk();
        $this->assertSame('0.00', $summaryAfter->viewData('salesTotal'));
        $this->assertSame(0, $summaryAfter->viewData('transactionCount'));
        $this->assertTrue($summaryAfter->viewData('quantitiesByUnit')->isEmpty());
        $this->assertTrue($this->get(route('reports.product-sales', $range))->assertOk()->viewData('rows')->isEmpty());
        $dashboardAfter = $this->get(route('home'))->assertOk();
        $this->assertSame('0.00', $dashboardAfter->viewData('todaySalesTotal'));
        $this->assertSame(0, $dashboardAfter->viewData('todayTransactionCount'));
        $this->assertFalse($dashboardAfter->viewData('recentSales')->contains('id', $sale->id));
        $todayTrend = collect($dashboardAfter->viewData('sevenDayTrend'))->firstWhere('date', '2026-09-26');
        $this->assertSame(0, $todayTrend['transaction_count']);
        $this->assertSame('0.00', $todayTrend['sales_total']);
    }

    public function test_real_sale_void_event_renders_safely_in_audit_log_viewer(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, $variant);
        $reason = 'PRIVATE VOID REASON MUST STAY OUT OF AUDIT';
        $this->actingAs($admin)->patch(route('sales.void', $sale), ['reason' => $reason]);

        $this->get(route('audit-logs.index'))->assertOk()
            ->assertSee(['Sale Voided', 'Sale #'.$sale->id, 'Status:', 'completed → voided'])
            ->assertDontSee($reason)
            ->assertDontSee('void_reason')
            ->assertDontSee('before_values')
            ->assertDontSee('after_values');
    }

    public function test_read_pages_and_unsupported_http_methods_cannot_void_or_write(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, $variant);
        $before = $this->domainState($sale, $variant);

        $this->actingAs($admin)->get(route('sales.index'))->assertOk();
        $this->get(route('sales.show', $sale))->assertOk();
        $this->assertSame($before, $this->domainState($sale, $variant));

        $url = route('sales.void', $sale);
        $this->get($url)->assertMethodNotAllowed();
        $this->post($url, ['reason' => 'Wrong method'])->assertMethodNotAllowed();
        $this->put($url, ['reason' => 'Wrong method'])->assertMethodNotAllowed();
        $this->delete($url, ['reason' => 'Wrong method'])->assertMethodNotAllowed();
        $this->assertSame($before, $this->domainState($sale, $variant));
    }

    private function recordSale(
        User $actor,
        ProductVariant $variant,
        string $quantity = '1.000',
        string $cash = '200.00',
    ): Sale {
        return app(RecordSale::class)->execute($actor, Str::uuid()->toString(), $cash, [[
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'expected_unit_price' => (string) $variant->selling_price,
        ]]);
    }

    private function voidMovements(Sale $sale)
    {
        return StockMovement::query()
            ->whereIn('sale_item_id', $sale->items()->pluck('id'))
            ->where('movement_type', StockMovement::TYPE_SALE_VOID)
            ->get();
    }

    private function voidAudits(Sale $sale)
    {
        return AuditLog::query()
            ->where('action', 'SALE_VOIDED')
            ->where('entity_type', 'sale')
            ->where('entity_id', $sale->id)
            ->get();
    }

    /** @return array<string, mixed> */
    private function domainState(Sale $sale, ProductVariant $variant): array
    {
        return [
            'sale' => $sale->fresh()->getAttributes(),
            'items' => $sale->items()->orderBy('id')->get()->map->getAttributes()->all(),
            'stock' => $variant->fresh()->current_stock,
            'movements' => StockMovement::query()->count(),
            'audits' => AuditLog::query()->count(),
            'sessions' => CashRegisterSession::query()->get()->map->getAttributes()->all(),
        ];
    }

    private function assertUnchanged(Sale $sale, ProductVariant $variant, string $stock): void
    {
        $fresh = $sale->fresh();
        $this->assertSame(Sale::STATUS_COMPLETED, $fresh->status);
        $this->assertNull($fresh->void_reason);
        $this->assertNull($fresh->voided_by);
        $this->assertNull($fresh->voided_at);
        $this->assertSame($stock, $variant->fresh()->current_stock);
        $this->assertSame(0, $this->voidMovements($sale)->count());
        $this->assertSame(0, $this->voidAudits($sale)->count());
    }
}

<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Sales\RecordSale;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SalesHistoryTest extends PosTestCase
{
    public function test_index_displays_authoritative_summary_and_deterministic_newest_first_order(): void
    {
        $cashier = User::factory()->create(['name' => 'History Cashier']);
        $product = $this->product($this->category(), ['name' => 'History Product']);
        $firstVariant = $this->variant($product, ['size' => 'First', 'current_stock' => '20.000']);
        $secondVariant = $this->variant($product, ['size' => 'Second', 'current_stock' => '20.000', 'selling_price' => '25.00']);
        $this->initialize($firstVariant, $cashier);
        $this->initialize($secondVariant, $cashier);

        $oldest = $this->at('2026-09-07 08:00:00', fn (): Sale => $this->recordSale($cashier, [
            [$firstVariant, '1', '100.00'],
        ], '100.00'));
        $middle = $this->at('2026-09-08 14:30:00', fn (): Sale => $this->recordSale($cashier, [
            [$firstVariant, '1', '100.00'],
        ], '150.00'));
        $newest = $this->at('2026-09-08 14:30:00', fn (): Sale => $this->recordSale($cashier, [
            [$firstVariant, '1', '100.00'],
            [$secondVariant, '2', '25.00'],
        ], '200.00'));

        $response = $this->actingAs($cashier)->get(route('sales.index'))->assertOk();
        $response->assertSeeInOrder([$newest->receiptNumber(), $middle->receiptNumber(), $oldest->receiptNumber()]);
        $response->assertSee('Sep 8, 2026 2:30 PM')
            ->assertSee('History Cashier')
            ->assertSee('completed')
            ->assertSee('Distinct items')
            ->assertSee('2')
            ->assertSee('₱150.00')
            ->assertSee('₱200.00')
            ->assertSee('₱50.00')
            ->assertSee('View receipt');
    }

    public function test_index_paginates_twenty_and_preserves_filters(): void
    {
        $cashier = User::factory()->create();
        $variant = $this->initializedVariant($cashier, ['current_stock' => '30.000']);
        $sales = collect();
        for ($index = 0; $index < 21; $index++) {
            $sales->push($this->at('2026-09-08 10:00:00', fn (): Sale => $this->recordSale($cashier, [
                [$variant, '1', '100.00'],
            ], '100.00')));
        }

        $response = $this->actingAs($cashier)->get(route('sales.index', [
            'cashier' => $cashier->id,
            'date_from' => '2026-09-01',
        ]))->assertOk();

        $response->assertSee($sales->last()->receiptNumber())
            ->assertDontSee($sales->first()->receiptNumber())
            ->assertSee('cashier='.$cashier->id, false)
            ->assertSee('date_from=2026-09-01', false);
        $this->actingAs($cashier)->get(route('sales.index', [
            'cashier' => $cashier->id,
            'date_from' => '2026-09-01',
            'page' => 2,
        ]))->assertOk()->assertSee($sales->first()->receiptNumber());
    }

    public function test_receipt_search_is_exact_canonical_case_insensitive_and_fails_closed(): void
    {
        $cashier = User::factory()->create();
        $variant = $this->initializedVariant($cashier);
        $first = $this->recordSale($cashier, [[$variant, '1', '100.00']], '100.00');
        $second = $this->recordSale($cashier, [[$variant, '1', '100.00']], '100.00');

        foreach (['TRX-000002', 'trx-000002'] as $receipt) {
            $this->actingAs($cashier)->get(route('sales.index', ['receipt' => $receipt]))
                ->assertOk()
                ->assertSee($second->receiptNumber())
                ->assertDontSee($first->receiptNumber());
        }

        $this->actingAs($cashier)->get(route('sales.index', ['receipt' => 'TRX-999999']))
            ->assertOk()->assertSee('No Sales found.')->assertDontSee($first->receiptNumber());

        foreach (['TRX-2', '000002', 'garbage', 'TRX-0000002'] as $receipt) {
            $this->actingAs($cashier)->get(route('sales.index', ['receipt' => $receipt]))
                ->assertOk()
                ->assertSee('No Sales found.')
                ->assertSee('receipt number')
                ->assertDontSee($first->receiptNumber())
                ->assertDontSee(route('sales.show', $second->id), false);
        }

        $this->actingAs($cashier)->get(route('sales.index', ['receipt' => ['TRX-000002']]))
            ->assertOk()->assertSee('No Sales found.')->assertSee('Enter a receipt number');
    }

    public function test_cashier_filter_is_narrow_and_includes_disabled_historical_cashiers(): void
    {
        $viewer = User::factory()->create();
        $disabledCashier = User::factory()->create([
            'name' => 'Disabled Historical Cashier',
            'username' => 'private_disabled_cashier',
        ]);
        $otherCashier = User::factory()->create(['name' => 'Other Historical Cashier']);
        $disabledVariant = $this->initializedVariant($disabledCashier);
        $otherVariant = $this->initializedVariant($otherCashier);
        $disabledSale = $this->recordSale($disabledCashier, [[$disabledVariant, '1', '100.00']], '100.00');
        $otherSale = $this->recordSale($otherCashier, [[$otherVariant, '1', '100.00']], '100.00');
        $disabledCashier->status = 'disabled';
        $disabledCashier->save();

        $this->actingAs($viewer)->get(route('sales.index', ['cashier' => $disabledCashier->id]))
            ->assertOk()
            ->assertSee('Disabled Historical Cashier')
            ->assertSee($disabledSale->receiptNumber())
            ->assertDontSee(route('sales.show', $otherSale->id), false)
            ->assertDontSee('private_disabled_cashier');
        $this->actingAs($viewer)->get(route('sales.index', ['cashier' => $otherCashier->id]))
            ->assertOk()->assertSee($otherSale->receiptNumber())->assertDontSee(route('sales.show', $disabledSale->id), false);
        $this->actingAs($viewer)->get(route('sales.index', ['cashier' => '999999']))
            ->assertOk()->assertSee('No Sales found.');
        $this->actingAs($viewer)->get(route('sales.index', ['cashier' => ['1']]))
            ->assertOk()->assertSee('Select a valid cashier.')->assertSee('No Sales found.');
    }

    public function test_date_filters_use_inclusive_manila_calendar_days_and_exclusive_next_day(): void
    {
        $cashier = User::factory()->create();
        $variant = $this->initializedVariant($cashier);
        $before = $this->at('2026-09-07 23:59:59', fn (): Sale => $this->recordSale($cashier, [[$variant, '1', '100.00']], '100.00'));
        $start = $this->at('2026-09-08 00:00:00', fn (): Sale => $this->recordSale($cashier, [[$variant, '1', '100.00']], '100.00'));
        $late = $this->at('2026-09-08 23:59:59', fn (): Sale => $this->recordSale($cashier, [[$variant, '1', '100.00']], '100.00'));
        $next = $this->at('2026-09-09 00:00:00', fn (): Sale => $this->recordSale($cashier, [[$variant, '1', '100.00']], '100.00'));

        $this->actingAs($cashier)->get(route('sales.index', ['date_from' => '2026-09-08']))
            ->assertOk()->assertDontSee($before->receiptNumber())->assertSee($start->receiptNumber())->assertSee($late->receiptNumber())->assertSee($next->receiptNumber());
        $this->actingAs($cashier)->get(route('sales.index', ['date_to' => '2026-09-08']))
            ->assertOk()->assertSee($before->receiptNumber())->assertSee($start->receiptNumber())->assertSee($late->receiptNumber())->assertDontSee($next->receiptNumber());
        $this->actingAs($cashier)->get(route('sales.index', ['date_from' => '2026-09-08', 'date_to' => '2026-09-08']))
            ->assertOk()->assertDontSee($before->receiptNumber())->assertSee($start->receiptNumber())->assertSee($late->receiptNumber())->assertDontSee($next->receiptNumber());
    }

    public function test_malformed_and_reversed_date_filters_fail_closed(): void
    {
        $cashier = User::factory()->create();
        $variant = $this->initializedVariant($cashier);
        $sale = $this->recordSale($cashier, [[$variant, '1', '100.00']], '100.00');

        foreach ([
            ['date_from' => '2026-02-31'],
            ['date_to' => 'not-a-date'],
            ['date_from' => '2026-09-09', 'date_to' => '2026-09-08'],
            ['date_from' => ['2026-09-08']],
        ] as $query) {
            $this->actingAs($cashier)->get(route('sales.index', $query))
                ->assertOk()->assertSee('No Sales found.')->assertDontSee($sale->receiptNumber());
        }
    }

    public function test_receipt_uses_snapshots_preserves_privacy_prints_and_never_writes(): void
    {
        Schema::table('restock_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->nullable();
        });

        $cashier = User::factory()->create(['name' => 'Receipt Cashier']);
        $viewer = User::factory()->create();
        $product = $this->product($this->category(), ['name' => 'Historical Hammer QZX']);
        $variant = $this->variant($product, [
            'size' => '18oz-QZX',
            'type_series' => 'Rip-QZX',
            'thickness' => '3mm-QZX',
            'unit' => 'piece',
            'cost_price' => '8765.43',
            'selling_price' => '123.45',
            'current_stock' => '10.000',
        ]);
        $this->initialize($variant, $cashier);
        $token = '123e4567-e89b-42d3-a456-426614174000';
        $sale = app(RecordSale::class)->execute($cashier, $token, '300.00', [[
            'product_variant_id' => $variant->id,
            'quantity' => '2',
            'expected_unit_price' => '123.45',
        ]]);

        $restockId = DB::table('restocks')->insertGetId(['recorded_by' => $cashier->id]);
        DB::table('restock_items')->insert([
            'restock_id' => $restockId,
            'product_variant_id' => $variant->id,
            'unit_cost' => '7654.32',
        ]);

        $product->name = 'Current Renamed Hammer QZX';
        $product->save();
        $variant->selling_price = '999.99';
        $variant->save();
        $this->recordSale($cashier, [[$variant, '1', '999.99']], '1000.00');

        $before = [
            Sale::query()->count(),
            SaleItem::query()->count(),
            StockMovement::query()->count(),
            AuditLog::query()->count(),
            $variant->fresh()->current_stock,
        ];

        foreach (range(1, 2) as $_) {
            $response = $this->actingAs($viewer)->get(route('sales.show', $sale->id))->assertOk();
            $response->assertSee($sale->receiptNumber())
                ->assertSee('Receipt Cashier')
                ->assertSee('completed')
                ->assertSee('Historical Hammer QZX')
                ->assertSee('18oz-QZX · Rip-QZX · 3mm-QZX')
                ->assertSee('piece')
                ->assertSee('2.000')
                ->assertSee('₱123.45')
                ->assertSee('₱246.90')
                ->assertSee('₱300.00')
                ->assertSee('₱53.10')
                ->assertSee('Print receipt')
                ->assertSee('onclick="window.print()"', false)
                ->assertSee('print:hidden', false)
                ->assertDontSee('Current Renamed Hammer QZX')
                ->assertDontSee('₱999.99')
                ->assertDontSee('8765.43')
                ->assertDontSee('7654.32')
                ->assertDontSee($token)
                ->assertDontSee('INITIAL_STOCK')
                ->assertDontSee('StockMovement')
                ->assertDontSee('Edit')
                ->assertDontSee('Delete')
                ->assertDontSee('Void');
        }

        $this->assertSame($before, [
            Sale::query()->count(),
            SaleItem::query()->count(),
            StockMovement::query()->count(),
            AuditLog::query()->count(),
            $variant->fresh()->current_stock,
        ]);
    }

    /**
     * @param  list<array{ProductVariant, string, string}>  $items
     */
    private function recordSale(User $cashier, array $items, string $cash): Sale
    {
        return app(RecordSale::class)->execute(
            $cashier,
            Str::uuid()->toString(),
            $cash,
            collect($items)->map(fn (array $item): array => [
                'product_variant_id' => $item[0]->id,
                'quantity' => $item[1],
                'expected_unit_price' => $item[2],
            ])->all(),
        );
    }

    private function at(string $dateTime, callable $operation): mixed
    {
        Carbon::setTestNow(Carbon::parse($dateTime, 'Asia/Manila'));

        try {
            return $operation();
        } finally {
            Carbon::setTestNow();
        }
    }
}

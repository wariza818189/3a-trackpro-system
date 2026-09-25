<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CashRegister\CloseCashRegister;
use App\Services\Sales\RecordSale;
use App\Services\Sales\SaleVoidService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class SaleVoidServiceTest extends PosTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
    }

    public function test_admin_voids_completed_sale_and_preserves_history_payment_cost_and_closed_register(): void
    {
        $admin = User::factory()->admin()->create();
        $session = $this->openCashRegister($admin, '50.00');
        $variant = $this->initializedVariant($admin, ['current_stock' => '10.000', 'cost_price' => '70.00']);
        $sale = $this->recordSale($admin, [[$variant, '2.000']], '300.00');
        app(CloseCashRegister::class)->execute($admin);

        $saleEvidence = $sale->only([
            'recorded_by', 'cash_register_session_id', 'total_amount', 'cash_received', 'change_amount', 'created_at',
        ]);
        $sessionEvidence = CashRegisterSession::query()->findOrFail($session->id)->getAttributes();
        $itemIds = $sale->items->pluck('id')->all();

        $result = app(SaleVoidService::class)->execute($admin, $sale, "  Customer   cancelled\norder  ");

        $this->assertSame(Sale::STATUS_VOIDED, $result->status);
        $this->assertSame('Customer cancelled order', $result->void_reason);
        $this->assertSame($admin->id, $result->voided_by);
        $this->assertNotNull($result->voided_at);
        foreach ($saleEvidence as $field => $value) {
            $this->assertSame((string) $value, (string) $result->{$field});
        }
        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame('70.00', $variant->fresh()->cost_price);
        $this->assertSame($itemIds, SaleItem::query()->where('sale_id', $sale->id)->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => Sale::STATUS_VOIDED]);
        $this->assertSame($sessionEvidence, CashRegisterSession::query()->findOrFail($session->id)->getAttributes());
    }

    public function test_service_revalidates_actor_authorization_inside_transaction(): void
    {
        $recorder = User::factory()->admin()->create();
        $this->openCashRegister($recorder);
        $variant = $this->initializedVariant($recorder);
        $sale = $this->recordSale($recorder, [[$variant, '1.000']]);

        $staff = User::factory()->create();
        $disabledAdmin = User::factory()->admin()->disabled()->create();
        $staleRole = User::factory()->admin()->create();
        $staleStatus = User::factory()->admin()->create();
        DB::table('users')->where('id', $staleRole->id)->update(['role' => 'staff']);
        DB::table('users')->where('id', $staleStatus->id)->update(['status' => 'disabled']);

        foreach ([$staff, $disabledAdmin, $staleRole, $staleStatus] as $actor) {
            $this->assertValidationError('actor', fn () => app(SaleVoidService::class)->execute($actor, $sale, 'Approved reason'));
        }

        $this->assertUnchangedVoidState($sale, $variant, '9.000');
    }

    public function test_second_void_and_inconsistent_completed_state_are_rejected_without_duplicate_effects(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, [[$variant, '2.000']]);
        app(SaleVoidService::class)->execute($admin, $sale, 'First void');

        $this->assertValidationError('sale', fn () => app(SaleVoidService::class)->execute($admin, $sale, 'Second void'));
        $this->assertSame('10.000', $variant->fresh()->current_stock);
        $this->assertSame(1, $this->voidMovements($sale)->count());
        $this->assertSame(1, $this->voidAudits($sale)->count());

        $otherVariant = $this->initializedVariant($admin, ['size' => 'Other']);
        $inconsistent = $this->recordSale($admin, [[$otherVariant, '1.000']]);
        DB::table('sales')->where('id', $inconsistent->id)->update(['void_reason' => 'orphan metadata']);
        $this->assertValidationError('sale', fn () => app(SaleVoidService::class)->execute($admin, $inconsistent, 'Reason'));
        $this->assertSame('9.000', $otherVariant->fresh()->current_stock);
    }

    public function test_reason_is_required_normalized_and_limited_by_character_count(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, [[$variant, '1.000']]);

        foreach (['', " \t\n "] as $reason) {
            $this->assertValidationError('reason', fn () => app(SaleVoidService::class)->execute($admin, $sale, $reason));
        }
        $this->assertValidationError('reason', fn () => app(SaleVoidService::class)->execute($admin, $sale, str_repeat('é', 1001)));

        $accepted = str_repeat('é', 1000);
        $result = app(SaleVoidService::class)->execute($admin, $sale, $accepted);
        $this->assertSame($accepted, $result->void_reason);
        $this->assertSame($accepted, $this->voidMovements($sale)->sole()->reason);
    }

    public function test_void_before_sale_time_is_rejected_without_mutation(): void
    {
        $admin = User::factory()->admin()->create();
        CarbonImmutable::setTestNow('2026-09-26 12:00:00');
        try {
            $this->openCashRegister($admin);
            $variant = $this->initializedVariant($admin);
            $sale = $this->recordSale($admin, [[$variant, '1.000']]);
            CarbonImmutable::setTestNow('2026-09-26 11:59:59');

            $this->assertValidationError('sale', fn () => app(SaleVoidService::class)->execute($admin, $sale, 'Chronology check'));
            $this->assertUnchangedVoidState($sale, $variant, '9.000');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_fractional_and_multiple_item_stock_is_restored_exactly_with_one_movement_per_item(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $whole = $this->initializedVariant($admin, ['current_stock' => '20.000', 'size' => 'Whole']);
        $fractional = $this->initializedVariant($admin, [
            'current_stock' => '10.125', 'quantity_mode' => 'fractional', 'unit' => 'kg', 'size' => 'Fractional',
        ]);
        $sale = $this->recordSale($admin, [[$whole, '3.000'], [$fractional, '0.375']], '500.00');

        app(SaleVoidService::class)->execute($admin, $sale, '  Full   reversal  ');

        $this->assertSame('20.000', $whole->fresh()->current_stock);
        $this->assertSame('10.125', $fractional->fresh()->current_stock);
        $movements = $this->voidMovements($sale)->keyBy('sale_item_id');
        $this->assertCount(2, $movements);
        foreach ($sale->items as $item) {
            $movement = $movements->get($item->id);
            $this->assertNotNull($movement);
            $this->assertSame(StockMovement::TYPE_SALE_VOID, $movement->movement_type);
            $this->assertSame($item->product_variant_id, $movement->product_variant_id);
            $this->assertSame($admin->id, $movement->performed_by);
            $this->assertSame((string) $item->quantity, $movement->quantity_change);
            $this->assertSame('Full reversal', $movement->reason);
            $this->assertNull($movement->restock_item_id);
            $this->assertSame($movement->quantity_after, bcadd($movement->quantity_before, $movement->quantity_change, 3));
        }
        $this->assertSame(2, StockMovement::query()->whereIn('sale_item_id', $sale->items->pluck('id'))->where('movement_type', StockMovement::TYPE_SALE)->count());
        $this->assertSame(2, StockMovement::query()->whereIn('sale_item_id', $sale->items->pluck('id'))->where('movement_type', StockMovement::TYPE_SALE_VOID)->count());
    }

    public function test_archived_catalog_hierarchy_does_not_block_restoration(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin, ['current_stock' => '2.000']);
        $sale = $this->recordSale($admin, [[$variant, '2.000']]);
        $product = Product::query()->findOrFail($variant->product_id);
        DB::table('product_variants')->where('id', $variant->id)->update(['status' => ProductVariant::STATUS_ARCHIVED]);
        DB::table('products')->where('id', $product->id)->update(['status' => 'archived']);
        DB::table('categories')->where('id', $product->category_id)->update(['status' => 'archived']);

        app(SaleVoidService::class)->execute($admin, $sale, 'Archived item reversal');

        $this->assertSame('2.000', $variant->fresh()->current_stock);
        $this->assertSame(ProductVariant::STATUS_ARCHIVED, $variant->fresh()->status);
    }

    public function test_sale_void_writes_one_status_only_private_audit_event(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, [[$variant, '1.000']], '150.00');
        $reason = 'Private customer explanation';

        app(SaleVoidService::class)->execute($admin, $sale, $reason);

        $audit = $this->voidAudits($sale)->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('SALE_VOIDED', $audit->action);
        $this->assertSame('sale', $audit->entity_type);
        $this->assertSame($sale->id, $audit->entity_id);
        $this->assertSame(['status' => Sale::STATUS_COMPLETED], $audit->before_values);
        $this->assertSame(['status' => Sale::STATUS_VOIDED], $audit->after_values);
        $this->assertSame("Sale #{$sale->id} was voided.", $audit->description);

        $serialized = json_encode($audit->only(['before_values', 'after_values', 'description']), JSON_THROW_ON_ERROR);
        foreach ([$reason, 'cash_received', 'change_amount', 'unit_price', 'checkout_token', 'submission_token', 'password'] as $private) {
            $this->assertStringNotContainsString($private, $serialized);
        }
    }

    public function test_movement_failure_rolls_back_stock_sale_and_all_void_evidence(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, [[$variant, '2.000']]);
        DB::statement("CREATE TRIGGER fail_sale_void_movement BEFORE INSERT ON stock_movements WHEN NEW.movement_type = 'SALE_VOID' BEGIN SELECT RAISE(ABORT, 'forced movement failure'); END");

        try {
            app(SaleVoidService::class)->execute($admin, $sale, 'Rollback movement');
            $this->fail('The forced movement failure was not raised.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced movement failure', $exception->getMessage());
        }

        $this->assertUnchangedVoidState($sale, $variant, '8.000');
    }

    public function test_audit_failure_rolls_back_sale_stock_and_movements(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, [[$variant, '2.000']]);
        DB::statement("CREATE TRIGGER fail_sale_void_audit BEFORE INSERT ON audit_logs WHEN NEW.action = 'SALE_VOIDED' BEGIN SELECT RAISE(ABORT, 'forced audit failure'); END");

        try {
            app(SaleVoidService::class)->execute($admin, $sale, 'Rollback audit');
            $this->fail('The forced audit failure was not raised.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced audit failure', $exception->getMessage());
        }

        $this->assertUnchangedVoidState($sale, $variant, '8.000');
    }

    public function test_stock_overflow_is_rejected_before_any_void_write(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin, ['current_stock' => '2.000']);
        $sale = $this->recordSale($admin, [[$variant, '1.000']]);
        DB::table('product_variants')->where('id', $variant->id)->update(['current_stock' => '99999999999.999']);

        $this->assertValidationError('sale', fn () => app(SaleVoidService::class)->execute($admin, $sale, 'Overflow'));

        $this->assertUnchangedVoidState($sale, $variant, '99999999999.999');
    }

    public function test_ordinary_sale_update_and_delete_remain_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $this->openCashRegister($admin);
        $variant = $this->initializedVariant($admin);
        $sale = $this->recordSale($admin, [[$variant, '1.000']]);

        try {
            $sale->status = Sale::STATUS_VOIDED;
            $sale->save();
            $this->fail('An ordinary Sale update unexpectedly succeeded.');
        } catch (LogicException $exception) {
            $this->assertSame('Historical records cannot be updated.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $sale->delete();
    }

    /** @param list<array{ProductVariant, string}> $lines */
    private function recordSale(User $actor, array $lines, string $cash = '500.00'): Sale
    {
        $sale = app(RecordSale::class)->execute(
            $actor,
            Str::uuid()->toString(),
            $cash,
            array_map(fn (array $line): array => [
                'product_variant_id' => $line[0]->id,
                'quantity' => $line[1],
                'expected_unit_price' => (string) $line[0]->selling_price,
            ], $lines),
        );

        return $sale->load('items');
    }

    private function voidMovements(Sale $sale)
    {
        return StockMovement::query()
            ->whereIn('sale_item_id', $sale->items()->pluck('id'))
            ->where('movement_type', StockMovement::TYPE_SALE_VOID)
            ->orderBy('id')
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

    private function assertUnchangedVoidState(Sale $sale, ProductVariant $variant, string $stock): void
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

    private function assertValidationError(string $field, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}

<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CashRegister\CloseCashRegister;
use App\Services\CashRegister\OpenCashRegister;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegisterTest extends PosTestCase
{
    public function test_closed_register_pos_shows_opening_form_and_gates_checkout_for_admin_and_staff(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $actor) {
            $response = $this->actingAs($actor)->get(route('pos.index'))->assertOk();

            $response->assertSee('Cash Register: Closed')
                ->assertSee('action="'.route('pos.register.open').'"', false)
                ->assertSee('name="opening_cash"', false)
                ->assertSee('Starting cash amount')
                ->assertSee('data-register-open="0"', false)
                ->assertSee('data-pos-checkout disabled', false)
                ->assertDontSee('action="'.route('pos.register.close').'"', false)
                ->assertDontSee('name="cash_register_session_id"', false);
        }
    }

    public function test_open_register_pos_shows_safe_state_and_role_appropriate_close_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-18 09:15:00', 'Asia/Manila'));

        try {
            $opener = User::factory()->create(['name' => 'Register Opener']);
            $otherStaff = User::factory()->create(['name' => 'Other Staff']);
            $admin = User::factory()->admin()->create(['name' => 'Store Admin']);
            app(OpenCashRegister::class)->execute($opener, '87654.32');

            $openerResponse = $this->actingAs($opener)->get(route('pos.index'))->assertOk();
            $openerResponse->assertSee('Cash Register: Open')
                ->assertSee('Opened by Register Opener')
                ->assertSee('Sep 18, 2026 9:15 AM')
                ->assertSee('data-register-open="1"', false)
                ->assertSee('action="'.route('pos.register.close').'"', false)
                ->assertDontSee('action="'.route('pos.register.open').'"', false)
                ->assertDontSee('name="opening_cash"', false)
                ->assertDontSee('87654.32')
                ->assertDontSee('name="cash_register_session_id"', false)
                ->assertDontSee('data-pos-checkout disabled', false);

            $this->actingAs($otherStaff)->get(route('pos.index'))
                ->assertOk()
                ->assertSee('Cash Register: Open')
                ->assertSee('Opened by Register Opener')
                ->assertSee('data-register-open="1"', false)
                ->assertDontSee('action="'.route('pos.register.close').'"', false)
                ->assertDontSee('data-pos-checkout disabled', false);

            $this->actingAs($admin)->get(route('pos.index'))
                ->assertOk()
                ->assertSee('Cash Register: Open')
                ->assertSee('action="'.route('pos.register.close').'"', false)
                ->assertDontSee('87654.32')
                ->assertDontSee('name="cash_register_session_id"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_can_open_register_with_canonical_authoritative_state_and_no_business_mutations(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:30:00', 'Asia/Manila'));

        try {
            $admin = User::factory()->admin()->create();
            $variant = $this->variant($this->product($this->category()), ['current_stock' => '7.000']);

            $response = $this->actingAs($admin)->post(route('pos.register.open'), [
                'opening_cash' => '00050.5',
            ]);

            $response->assertSessionHasNoErrors()
                ->assertRedirect(route('pos.index'))
                ->assertSessionHas('success', 'Cash register opened.');
            $this->get(route('pos.index'))
                ->assertOk()
                ->assertSee('Cash register opened.')
                ->assertSee('Cash Register: Open');

            $session = CashRegisterSession::query()->sole();
            $this->assertSame('50.50', $session->opening_cash);
            $this->assertSame($admin->id, $session->opened_by);
            $this->assertTrue($session->opened_at->equalTo(now()));
            $this->assertSame(1, $session->active_slot);
            $this->assertNull($session->closed_by);
            $this->assertNull($session->closed_at);
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, SaleItem::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
            $this->assertSame(0, AuditLog::query()->count());
            $this->assertSame('7.000', $variant->fresh()->current_stock);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_staff_can_open_register_and_zero_is_accepted(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->post(route('pos.register.open'), ['opening_cash' => '0'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pos.index'));

        $session = CashRegisterSession::query()->sole();
        $this->assertSame('0.00', $session->opening_cash);
        $this->assertSame($staff->id, $session->opened_by);
        $this->assertSame(1, $session->active_slot);
    }

    public function test_open_request_rejects_noncanonical_and_out_of_range_values_without_creating_a_session(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->post(route('pos.register.open'), [])
            ->assertSessionHasErrors('opening_cash');

        foreach (['-1', '+1', '1e2', '1,000', '.50', '50.', '1.001', '100000000000000.00'] as $invalid) {
            $this->actingAs($actor)->post(route('pos.register.open'), ['opening_cash' => $invalid])
                ->assertSessionHasErrors('opening_cash');
        }

        foreach ([['50'], 50, 50.0] as $nonString) {
            $this->actingAs($actor)->post(route('pos.register.open'), ['opening_cash' => $nonString])
                ->assertSessionHasErrors('opening_cash');
        }

        $this->assertSame(0, CashRegisterSession::query()->count());
    }

    public function test_open_request_accepts_decimal_column_maximum_lexically(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->post(route('pos.register.open'), [
            'opening_cash' => '99999999999999.99',
        ])->assertSessionHasNoErrors()->assertRedirect(route('pos.index'));

        // SQLite numeric affinity cannot prove MySQL DECIMAL(16,2) storage at this boundary.
        $this->assertSame(1, CashRegisterSession::query()->count());
    }

    public function test_open_request_rejects_browser_supplied_authoritative_and_unexpected_fields(): void
    {
        $actor = User::factory()->create();
        $protected = [
            'id' => '9',
            'cash_register_session_id' => '9',
            'register_id' => '9',
            'opened_by' => '9',
            'opened_at' => '2026-09-17 08:00:00',
            'active_slot' => '1',
            'closed_by' => '9',
            'closed_at' => '2026-09-17 09:00:00',
            'unexpected' => 'value',
        ];

        $this->actingAs($actor)->post(route('pos.register.open'), array_merge([
            'opening_cash' => '100.00',
        ], $protected))->assertSessionHasErrors([
            'id',
            'cash_register_session_id',
            'register_id',
            'opened_by',
            'opened_at',
            'active_slot',
            'closed_by',
            'closed_at',
            'request',
        ]);

        $this->assertSame(0, CashRegisterSession::query()->count());
    }

    public function test_sequential_second_open_is_controlled_and_preserves_first_session(): void
    {
        $firstActor = User::factory()->create();
        $secondActor = User::factory()->admin()->create();
        $first = app(OpenCashRegister::class)->execute($firstActor, '125.00');
        $before = $first->fresh()->getAttributes();

        $this->actingAs($secondActor)->from(route('pos.index'))->post(route('pos.register.open'), [
            'opening_cash' => '250.00',
        ])->assertSessionHasErrors([
            'opening_cash' => 'The cash register is already open.',
        ])->assertRedirect(route('pos.index'));

        $this->assertSame(1, CashRegisterSession::query()->count());
        $this->assertSame($before, $first->fresh()->getAttributes());
    }

    public function test_staff_opener_can_close_authoritative_session_without_reconciliation_or_business_mutations(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00', 'Asia/Manila'));

        try {
            $staff = User::factory()->create();
            $variant = $this->variant($this->product($this->category()), ['current_stock' => '11.000']);
            $opened = app(OpenCashRegister::class)->execute($staff, '300.00');
            $opened->refresh();
            $openingState = [
                'opening_cash' => $opened->getRawOriginal('opening_cash'),
                'opened_by' => $opened->getRawOriginal('opened_by'),
                'opened_at' => $opened->getRawOriginal('opened_at'),
            ];

            Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'Asia/Manila'));
            $this->actingAs($staff)->post(route('pos.register.close'), [
                'cash_register_session_id' => 999999,
                'closing_cash' => '999.99',
                'shortage' => '1.00',
            ])->assertSessionHasNoErrors()
                ->assertRedirect(route('pos.index'))
                ->assertSessionHas('success', 'Cash register closed.');
            $this->get(route('pos.index'))
                ->assertOk()
                ->assertSee('Cash register closed.')
                ->assertSee('Cash Register: Closed');

            $closed = $opened->fresh();
            $this->assertSame($openingState['opening_cash'], $closed->getRawOriginal('opening_cash'));
            $this->assertSame($openingState['opened_by'], $closed->getRawOriginal('opened_by'));
            $this->assertSame($openingState['opened_at'], $closed->getRawOriginal('opened_at'));
            $this->assertSame($staff->id, $closed->closed_by);
            $this->assertTrue($closed->closed_at->equalTo(now()));
            $this->assertNull($closed->active_slot);
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, SaleItem::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
            $this->assertSame(0, AuditLog::query()->count());
            $this->assertSame('11.000', $variant->fresh()->current_stock);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_can_close_a_staff_opened_session(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $opened = app(OpenCashRegister::class)->execute($staff, '100.00');

        $this->actingAs($admin)->post(route('pos.register.close'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pos.index'));

        $closed = $opened->fresh();
        $this->assertSame($admin->id, $closed->closed_by);
        $this->assertNotNull($closed->closed_at);
        $this->assertNull($closed->active_slot);
    }

    public function test_different_staff_cannot_close_another_staff_members_session(): void
    {
        $opener = User::factory()->create();
        $other = User::factory()->create();
        $opened = app(OpenCashRegister::class)->execute($opener, '100.00');
        $before = $opened->fresh()->getAttributes();

        $this->actingAs($other)->post(route('pos.register.close'))->assertForbidden();

        $this->assertSame($before, $opened->fresh()->getAttributes());
        $this->assertSame(1, CashRegisterSession::query()->where('active_slot', 1)->count());
    }

    public function test_close_without_active_session_is_controlled_and_does_not_create_one(): void
    {
        $actor = User::factory()->admin()->create();

        $this->actingAs($actor)->from(route('pos.index'))->post(route('pos.register.close'))
            ->assertSessionHasErrors([
                'register' => 'No active cash register session is available to close.',
            ])->assertRedirect(route('pos.index'));

        $this->assertSame(0, CashRegisterSession::query()->count());
    }

    public function test_repeated_close_uses_the_normal_no_active_session_failure(): void
    {
        $actor = User::factory()->create();
        app(OpenCashRegister::class)->execute($actor, '100.00');

        $this->actingAs($actor)->post(route('pos.register.close'))->assertSessionHasNoErrors();
        $this->actingAs($actor)->from(route('pos.index'))->post(route('pos.register.close'))
            ->assertSessionHasErrors('register')
            ->assertRedirect(route('pos.index'));

        $this->assertSame(1, CashRegisterSession::query()->count());
        $this->assertSame(0, CashRegisterSession::query()->where('active_slot', 1)->count());
    }

    public function test_services_revalidate_persisted_actor_state(): void
    {
        $disabled = User::factory()->disabled()->create();
        $missing = new User;
        $missing->id = 999999;
        $missing->exists = true;

        foreach ([$disabled, $missing] as $actor) {
            $this->assertActorValidation(fn () => app(OpenCashRegister::class)->execute($actor, '100.00'));
        }

        $active = User::factory()->create();
        $opened = app(OpenCashRegister::class)->execute($active, '100.00');
        $active->status = 'disabled';
        $active->save();

        $this->assertActorValidation(fn () => app(CloseCashRegister::class)->execute($active));
        $this->assertSame(1, $opened->fresh()->active_slot);
        $this->assertNull($opened->fresh()->closed_by);
    }

    public function test_unrelated_database_failure_is_not_converted_to_already_open_validation(): void
    {
        $actor = User::factory()->create();
        DB::unprepared("CREATE TRIGGER fail_cash_register_insert BEFORE INSERT ON cash_register_sessions BEGIN SELECT RAISE(ABORT, 'forced cash register failure'); END");

        try {
            app(OpenCashRegister::class)->execute($actor, '100.00');
            $this->fail('The unrelated database failure should have been rethrown.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced cash register failure', $exception->getMessage());
        }

        $this->assertSame(0, CashRegisterSession::query()->count());
    }

    private function assertActorValidation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The service accepted an actor without persisted active operational authority.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('actor', $exception->errors());
        }
    }
}

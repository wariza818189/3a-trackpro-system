<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Services\Sales\RecordSale;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SalesHistoryAuthorizationTest extends PosTestCase
{
    public function test_guest_is_denied_sales_history_and_receipt(): void
    {
        $this->get(route('sales.index'))->assertRedirect('/login');
        $this->get(route('sales.show', 1))->assertRedirect('/login');
    }

    public function test_disabled_user_is_denied_and_logged_out(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)->get(route('sales.index'))->assertRedirect('/login');
        $this->assertGuest();
        $this->actingAs($user)->get(route('sales.show', 1))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_active_admin_and_staff_can_view_all_sales(): void
    {
        $cashier = User::factory()->create(['name' => 'Other Cashier']);
        $variant = $this->initializedVariant($cashier);
        $sale = app(RecordSale::class)->execute($cashier, Str::uuid()->toString(), '100', [[
            'product_variant_id' => $variant->id,
            'quantity' => '1',
            'expected_unit_price' => '100',
        ]]);

        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $viewer) {
            $this->actingAs($viewer)->get(route('sales.index'))
                ->assertOk()
                ->assertSee($sale->receiptNumber())
                ->assertSee('Other Cashier');
            $this->actingAs($viewer)->get(route('sales.show', $sale->id))
                ->assertOk()
                ->assertSee($sale->receiptNumber())
                ->assertSee('Other Cashier');
        }
    }

    public function test_admin_and_staff_navigation_contains_sales_history(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('Sales History');
        }
    }

    public function test_sales_routes_are_get_only_and_have_no_admin_gate_or_mutation_surface(): void
    {
        foreach (['sales.index', 'sales.show'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('active', $route->gatherMiddleware());
            $this->assertNotContains('can:access-admin', $route->gatherMiddleware());
        }

        foreach (['sales.edit', 'sales.update', 'sales.destroy', 'sales.receipt', 'sales.print', 'sales.reprint', 'sales.void'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }

        $this->assertSame(2, collect(Route::getRoutes())->filter(
            fn ($route): bool => str_starts_with((string) $route->getName(), 'sales.'),
        )->count());
    }

    public function test_malformed_and_missing_sale_ids_return_controlled_not_found_responses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sales/foo')->assertNotFound();
        $this->actingAs($user)->get('/sales/0')->assertNotFound();
        $this->actingAs($user)->get('/sales/999999')->assertNotFound();
    }
}

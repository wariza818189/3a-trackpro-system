<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

class StockCorrectionAuthorizationTest extends StockCorrectionTestCase
{
    public function test_guest_is_denied_every_stock_correction_route(): void
    {
        $variant = $this->variant($this->product($this->category()));

        $this->get(route('stock-corrections.index'))->assertRedirect('/login');
        $this->get(route('stock-corrections.create', $variant))->assertRedirect('/login');
        $this->post(route('stock-corrections.store', $variant))->assertRedirect('/login');
    }

    public function test_staff_is_forbidden_and_has_no_stock_correction_navigation(): void
    {
        $staff = User::factory()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $movement = $this->initialize($variant, $staff);

        $this->actingAs($staff)->get(route('home'))->assertOk()->assertDontSee('Stock Correction');
        $this->actingAs($staff)->get(route('stock-corrections.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('stock-corrections.create', $variant))->assertForbidden();
        $this->actingAs($staff)->post(route('stock-corrections.store', $variant), $this->payload($movement))->assertForbidden();
    }

    public function test_disabled_admin_is_denied(): void
    {
        $activeAdmin = User::factory()->admin()->create();
        $disabledAdmin = User::factory()->admin()->disabled()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $movement = $this->initialize($variant, $activeAdmin);

        $this->actingAs($disabledAdmin)->get(route('stock-corrections.index'))->assertRedirect('/login');
        $this->actingAs($disabledAdmin)->get(route('stock-corrections.create', $variant))->assertRedirect('/login');
        $this->actingAs($disabledAdmin)->post(route('stock-corrections.store', $variant), $this->payload($movement))->assertRedirect('/login');
    }

    public function test_active_admin_can_view_and_record_with_fresh_movement_version_and_csrf(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '10.000']);
        $movement = $this->initialize($variant, $admin);

        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee('Stock Correction');
        $this->actingAs($admin)->get(route('stock-corrections.index'))
            ->assertOk()
            ->assertSee('Initialized active variants')
            ->assertSee('Correct stock');
        $this->withSession(['_old_input' => ['expected_movement_id' => '999999']])
            ->actingAs($admin)
            ->get(route('stock-corrections.create', $variant))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="expected_movement_id" value="'.$movement->id.'"', false)
            ->assertDontSee('name="expected_movement_id" value="999999"', false);

        $this->actingAs($admin)->post(route('stock-corrections.store', $variant), $this->payload($movement))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('stock-corrections.index'));
    }

    public function test_routes_have_admin_middleware_csrf_and_no_historical_mutations(): void
    {
        app(Kernel::class);

        foreach (['stock-corrections.index', 'stock-corrections.create', 'stock-corrections.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertContains('can:access-admin', $middleware);
        }

        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('stock-corrections.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('stock-corrections.create')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('stock-corrections.store')->methods());
        foreach (['stock-corrections.edit', 'stock-corrections.update', 'stock-corrections.destroy'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }
        $this->assertContains(PreventRequestForgery::class, app('router')->getMiddlewareGroups()['web']);
        $this->assertSame([], app(PreventRequestForgery::class)->getExcludedPaths());
    }
}

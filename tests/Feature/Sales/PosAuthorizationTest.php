<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class PosAuthorizationTest extends PosTestCase
{
    public function test_guest_is_denied_pos_and_checkout(): void
    {
        $this->get(route('pos.index'))->assertRedirect('/login');
        $this->post(route('pos.checkout'))->assertRedirect('/login');
        $this->post(route('pos.register.open'), ['opening_cash' => '0'])->assertRedirect('/login');
        $this->post(route('pos.register.close'))->assertRedirect('/login');
    }

    public function test_disabled_user_is_denied_and_logged_out(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)->get(route('pos.index'))->assertRedirect('/login');
        $this->assertGuest();
        $this->actingAs($user)->post(route('pos.checkout'))->assertRedirect('/login');
        $this->assertGuest();
        $this->actingAs($user)->post(route('pos.register.open'), ['opening_cash' => '0'])->assertRedirect('/login');
        $this->assertGuest();
        $this->actingAs($user)->post(route('pos.register.close'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_active_admin_and_staff_can_view_and_checkout(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        $this->openCashRegister($admin);

        foreach ([$admin, $staff] as $user) {
            $variant = $this->initializedVariant($user);
            $this->actingAs($user)->get(route('pos.index'))
                ->assertOk()->assertSee('Point of Sale')->assertSee('name="_token"', false);
            $this->actingAs($user)->post(route('pos.checkout'), $this->payload($variant))
                ->assertSessionHasNoErrors()->assertRedirect(route('pos.index'));
        }
    }

    public function test_admin_and_staff_navigation_contains_pos(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('POS');
        }
    }

    public function test_pos_routes_have_auth_and_active_without_admin_gate_and_no_unapproved_sale_routes(): void
    {
        foreach (['pos.index', 'pos.checkout', 'pos.register.open', 'pos.register.close'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertNotContains('can:access-admin', $middleware);
        }
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('pos.index')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('pos.checkout')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('pos.register.open')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('pos.register.close')->methods());
        foreach (['sales.edit', 'sales.update', 'sales.destroy', 'sales.receipt'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }
    }
}

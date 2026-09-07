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
    }

    public function test_disabled_user_is_denied_and_logged_out(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)->get(route('pos.index'))->assertRedirect('/login');
        $this->assertGuest();
        $this->actingAs($user)->post(route('pos.checkout'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_active_admin_and_staff_can_view_and_checkout(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
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

    public function test_pos_routes_have_auth_and_active_without_admin_gate_and_no_future_routes(): void
    {
        foreach (['pos.index', 'pos.checkout'] as $name) {
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
        foreach (['sales.index', 'sales.show', 'sales.edit', 'sales.update', 'sales.destroy', 'sales.receipt', 'sales.void'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }
    }
}

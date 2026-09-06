<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class RestockAuthorizationTest extends RestockTestCase
{
    public function test_guest_is_denied_every_stock_in_route(): void
    {
        $this->get(route('stock-in.index'))->assertRedirect('/login');
        $this->get(route('stock-in.create'))->assertRedirect('/login');
        $this->post(route('stock-in.store'))->assertRedirect('/login');
        $this->get(route('stock-in.show', 1))->assertRedirect('/login');
    }

    public function test_disabled_user_is_denied(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)->get(route('stock-in.index'))->assertRedirect('/login');
        $this->actingAs($user)->post(route('stock-in.store'))->assertRedirect('/login');
    }

    public function test_admin_and_staff_can_view_and_post_stock_in(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $index => $user) {
            $variant = $this->variant($this->product($this->category()), ['size' => 'Role '.$index]);
            $this->initialize($variant, $user);

            $this->actingAs($user)->get(route('stock-in.index'))->assertOk()->assertSee('Stock In');
            $this->actingAs($user)->get(route('stock-in.create'))->assertOk()->assertSee('name="_token"', false);
            $this->actingAs($user)->post(route('stock-in.store'), $this->payload($variant))
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }
    }

    public function test_staff_navigation_contains_stock_in_but_no_correction(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get(route('home'))
            ->assertOk()
            ->assertSee('Stock In')
            ->assertDontSee('Correction');
    }

    public function test_routes_have_active_auth_without_admin_gate_or_historical_mutations(): void
    {
        foreach (['stock-in.index', 'stock-in.create', 'stock-in.store', 'stock-in.show'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertNotContains('can:access-admin', $middleware);
        }

        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('stock-in.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('stock-in.create')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('stock-in.store')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('stock-in.show')->methods());
        foreach (['stock-in.edit', 'stock-in.update', 'stock-in.destroy'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }
    }
}

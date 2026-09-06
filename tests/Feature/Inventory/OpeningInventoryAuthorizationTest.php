<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Catalog\CatalogTestCase;

class OpeningInventoryAuthorizationTest extends CatalogTestCase
{
    public function test_guest_cannot_access_opening_inventory(): void
    {
        $variant = $this->variant($this->product($this->category()));

        $this->get(route('opening-inventory.index'))->assertRedirect('/login');
        $this->get(route('opening-inventory.create', $variant))->assertRedirect('/login');
        $this->post(route('opening-inventory.store', $variant), $this->payload())->assertRedirect('/login');
    }

    public function test_staff_is_forbidden_and_has_no_opening_inventory_navigation(): void
    {
        $staff = User::factory()->create();
        $variant = $this->variant($this->product($this->category()));

        $this->actingAs($staff)->get(route('home'))->assertOk()->assertDontSee('Opening Inventory');
        $this->actingAs($staff)->get(route('opening-inventory.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('opening-inventory.create', $variant))->assertForbidden();
        $this->actingAs($staff)->post(route('opening-inventory.store', $variant), $this->payload())->assertForbidden();
    }

    public function test_admin_can_open_index_and_create_form_with_csrf(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()));

        $this->actingAs($admin)->get(route('opening-inventory.index'))
            ->assertOk()
            ->assertSee('Opening Inventory')
            ->assertSee('Record opening inventory');
        $this->actingAs($admin)->get(route('opening-inventory.create', $variant))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_disabled_admin_is_denied(): void
    {
        $disabledAdmin = User::factory()->admin()->disabled()->create();
        $variant = $this->variant($this->product($this->category()));

        $this->actingAs($disabledAdmin)->get(route('opening-inventory.index'))->assertRedirect('/login');
        $this->actingAs($disabledAdmin)->post(route('opening-inventory.store', $variant), $this->payload())->assertRedirect('/login');
    }

    public function test_routes_have_admin_middleware_and_only_post_mutates(): void
    {
        app(Kernel::class);

        foreach (['opening-inventory.index', 'opening-inventory.create', 'opening-inventory.store'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertContains('can:access-admin', $middleware);
        }

        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('opening-inventory.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('opening-inventory.create')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('opening-inventory.store')->methods());
        $this->assertContains(PreventRequestForgery::class, app('router')->getMiddlewareGroups()['web']);
        $this->assertSame([], app(PreventRequestForgery::class)->getExcludedPaths());
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return ['opening_quantity' => '0', 'reason' => 'Initial physical count'];
    }
}

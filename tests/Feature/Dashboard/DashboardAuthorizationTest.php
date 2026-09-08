<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Sales\PosTestCase;

class DashboardAuthorizationTest extends PosTestCase
{
    public function test_guest_is_denied_dashboard(): void
    {
        $this->get(route('home'))->assertRedirect('/login');
    }

    public function test_disabled_user_is_denied_and_logged_out(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)->get(route('home'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_active_admin_and_staff_can_view_dashboard_with_role_appropriate_content(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();

        $this->actingAs($admin)->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Seven-day Completed Sales Trend');

        $this->actingAs($staff)->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('Seven-day Completed Sales Trend');
    }

    public function test_home_route_remains_get_only_without_admin_gate(): void
    {
        $route = Route::getRoutes()->getByName('home');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('App\\Http\\Controllers\\DashboardController@index', $route->getActionName());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertNotContains('can:access-admin', $route->gatherMiddleware());
    }
}

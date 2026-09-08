<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Sales\PosTestCase;

class ReportsAuthorizationTest extends PosTestCase
{
    public function test_guest_is_denied_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect('/login');
    }

    public function test_disabled_user_is_denied_and_logged_out(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)->get(route('reports.index'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_staff_is_forbidden_and_admin_can_view_reports(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()->assertSee('Sales Summary');
    }

    public function test_reports_navigation_is_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();

        $this->actingAs($admin)->get(route('home'))
            ->assertOk()
            ->assertSee('data-nav-route="reports.index"', false)
            ->assertSee('Reports');
        $this->actingAs($staff)->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-nav-route="reports.index"', false)
            ->assertDontSee('Reports');
    }

    public function test_reports_route_is_get_only_and_admin_protected(): void
    {
        $route = Route::getRoutes()->getByName('reports.index');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('App\\Http\\Controllers\\ReportsController@index', $route->getActionName());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertContains('can:access-admin', $route->gatherMiddleware());
        $this->assertNull(Route::getRoutes()->getByName('reports.store'));
        $this->assertNull(Route::getRoutes()->getByName('reports.export'));
        $this->assertNull(Route::getRoutes()->getByName('reports.print'));
    }
}

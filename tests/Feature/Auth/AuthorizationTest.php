<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class AuthorizationTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active', 'can:access-admin'])
            ->get('/_test/admin-only', fn () => response('admin access granted'))
            ->name('test.admin-only');
    }

    public function test_guest_cannot_access_the_protected_application_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_active_staff_can_access_the_normal_application_page(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get('/')
            ->assertOk()
            ->assertSee($staff->name);
    }

    public function test_active_admin_can_access_an_admin_protected_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/_test/admin-only')
            ->assertOk()
            ->assertSee('admin access granted');
    }

    public function test_staff_direct_request_to_admin_route_is_forbidden(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get('/_test/admin-only')
            ->assertForbidden();
    }

    public function test_disabled_account_is_rejected_by_backend_status_middleware(): void
    {
        $disabled = User::factory()->disabled()->create();

        $this->actingAs($disabled)
            ->withSession(['protected_state' => 'discard-me'])
            ->get('/')
            ->assertRedirect('/login')
            ->assertSessionMissing('protected_state');

        $this->assertGuest();
    }

    public function test_disabled_account_receives_an_unauthenticated_json_response(): void
    {
        $disabled = User::factory()->disabled()->create();

        $this->actingAs($disabled)
            ->getJson('/')
            ->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_user_disabled_after_login_is_rejected_on_the_next_real_request(): void
    {
        $user = User::factory()->create([
            'username' => 'soon_disabled',
            'password' => 'correct-password',
        ]);
        $this->withSession(['protected_state' => 'discard-me']);

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'correct-password',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        User::query()->whereKey($user->id)->update(['status' => 'disabled']);

        $this->get('/')
            ->assertRedirect('/login')
            ->assertSessionMissing('protected_state');

        $this->assertGuest();
    }
}

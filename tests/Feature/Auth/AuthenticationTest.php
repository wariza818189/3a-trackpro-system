<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class AuthenticationTest extends AuthTestCase
{
    public function test_guest_sees_username_password_and_csrf_login_fields(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="remember"', false);
    }

    public function test_active_admin_can_log_in(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'admin-test-password']);

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'admin-test-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_active_staff_can_log_in(): void
    {
        $staff = User::factory()->create(['password' => 'staff-test-password']);

        $this->post('/login', [
            'username' => $staff->username,
            'password' => 'staff-test-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($staff);
    }

    public function test_login_uses_the_model_username_normalization_rule(): void
    {
        $staff = User::factory()->create([
            'username' => 'normalized_user',
            'password' => 'staff-test-password',
        ]);

        $this->post('/login', [
            'username' => '  NORMALIZED_USER  ',
            'password' => 'staff-test-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($staff);
    }

    public function test_all_credential_failures_use_the_same_generic_message(): void
    {
        $active = User::factory()->create([
            'username' => 'active_user',
            'password' => 'correct-password',
        ]);
        $disabled = User::factory()->disabled()->create([
            'username' => 'disabled_user',
            'password' => 'correct-password',
        ]);

        $attempts = [
            ['username' => 'missing_user', 'password' => 'wrong-password'],
            ['username' => $active->username, 'password' => 'wrong-password'],
            ['username' => $disabled->username, 'password' => 'correct-password'],
        ];

        foreach ($attempts as $credentials) {
            $this->post('/login', $credentials)
                ->assertSessionHasErrors([
                    'username' => LoginRequest::AUTHENTICATION_FAILED_MESSAGE,
                ]);
            $this->assertGuest();
        }
    }

    public function test_successful_login_explicitly_changes_the_session_id(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->withSession(['pre_login_state' => 'present']);
        $oldSessionId = session()->getId();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'correct-password',
        ])->assertRedirect('/');

        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/');
    }

    public function test_login_redirects_to_the_intended_protected_location(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->get('/')->assertRedirect('/login');

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'correct-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_only_authentication_cannot_succeed(): void
    {
        User::factory()->create([
            'username' => 'username_only',
            'password' => 'correct-password',
        ]);

        $this->post('/login', [
            'email' => 'username_only@example.test',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout_removes_authentication_invalidates_session_and_rotates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'protected_state' => 'discard-me',
            '_token' => 'old-csrf-token',
        ]);
        $oldSessionId = session()->getId();

        $this->post('/logout')
            ->assertRedirect('/login')
            ->assertSessionMissing('protected_state');

        $this->assertGuest();
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame('old-csrf-token', session()->token());
    }

    public function test_protected_page_renders_a_post_logout_form_with_csrf_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_logout_has_no_get_route(): void
    {
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_login_and_logout_use_csrf_protected_web_middleware_without_exclusions(): void
    {
        app(Kernel::class);

        foreach (['login.store', 'logout'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertContains('web', $route->gatherMiddleware());
        }

        $this->assertContains(
            PreventRequestForgery::class,
            app('router')->getMiddlewareGroups()['web'],
        );
        $this->assertSame([], app(PreventRequestForgery::class)->getExcludedPaths());
    }

    public function test_five_failures_are_counted_and_the_next_attempt_is_throttled(): void
    {
        for ($attempt = 1; $attempt <= LoginRequest::MAX_ATTEMPTS; $attempt++) {
            $this->post('/login', [
                'username' => 'missing_user',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'username' => LoginRequest::AUTHENTICATION_FAILED_MESSAGE,
            ]);
        }

        $key = LoginRequest::throttleKeyFor('missing_user', '127.0.0.1');
        $this->assertSame(LoginRequest::MAX_ATTEMPTS, RateLimiter::attempts($key));

        $response = $this->post('/login', [
            'username' => 'missing_user',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertStringStartsWith(
            'Too many login attempts. Please try again in ',
            $response->getSession()->get('errors')->first('username'),
        );
        $this->assertSame(LoginRequest::MAX_ATTEMPTS, RateLimiter::attempts($key));
    }

    public function test_normalized_username_variants_share_a_rate_limit_key(): void
    {
        foreach (['  MISSING_USER  ', 'missing_user'] as $username) {
            $this->post('/login', [
                'username' => $username,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $key = LoginRequest::throttleKeyFor('missing_user', '127.0.0.1');
        $this->assertSame(2, RateLimiter::attempts($key));
        $this->assertSame(
            $key,
            LoginRequest::throttleKeyFor('  MISSING_USER  ', '127.0.0.1'),
        );
    }

    public function test_each_credential_failure_type_consumes_a_rate_limit_attempt(): void
    {
        $active = User::factory()->create([
            'username' => 'active_user',
            'password' => 'correct-password',
        ]);
        $disabled = User::factory()->disabled()->create([
            'username' => 'disabled_user',
            'password' => 'correct-password',
        ]);

        foreach ([
            ['missing_user', 'wrong-password'],
            [$active->username, 'wrong-password'],
            [$disabled->username, 'correct-password'],
        ] as [$username, $password]) {
            $this->post('/login', compact('username', 'password'));

            $this->assertSame(
                1,
                RateLimiter::attempts(LoginRequest::throttleKeyFor($username, '127.0.0.1')),
            );
        }
    }

    public function test_successful_login_clears_previous_failures(): void
    {
        $user = User::factory()->create([
            'username' => 'limited_user',
            'password' => 'correct-password',
        ]);
        $key = LoginRequest::throttleKeyFor($user->username, '127.0.0.1');

        $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password']);
        $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password']);
        $this->assertSame(2, RateLimiter::attempts($key));

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'correct-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_forbidden_public_authentication_and_bootstrap_routes_do_not_exist(): void
    {
        $paths = [
            '/register',
            '/forgot-password',
            '/reset-password',
            '/email/verify',
            '/setup',
            '/migrate',
            '/seed',
            '/reset-admin',
            '/debug-users',
            '/users',
            '/trackpro/create-admin',
        ];

        foreach ($paths as $path) {
            $this->get($path)->assertNotFound();
            $this->post($path)->assertNotFound();
        }

        $this->get('/logout')->assertMethodNotAllowed();
    }
}

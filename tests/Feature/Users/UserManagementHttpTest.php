<?php

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Auth\AuthTestCase;

class UserManagementHttpTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('description');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_all_routes_use_admin_middleware_and_index_denies_guest_staff_and_disabled_actor(): void
    {
        foreach (['users.index', 'users.create', 'users.store', 'users.edit', 'users.update', 'users.role', 'users.archive', 'users.reactivate', 'users.password'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('active', $route->gatherMiddleware());
            $this->assertContains('can:access-admin', $route->gatherMiddleware());
        }

        $this->get(route('users.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route('users.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->disabled()->create())
            ->get(route('users.index'))->assertRedirect('/login');
        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();
    }

    public function test_admin_navigation_shows_users_and_staff_navigation_hides_it(): void
    {
        $this->actingAs($this->admin())->get(route('home'))->assertOk()->assertSee('User Management');
        $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertDontSee('User Management');
    }

    public function test_index_shows_active_and_disabled_accounts_without_secrets(): void
    {
        $actor = $this->admin();
        $active = User::factory()->create(['name' => 'Active Person', 'username' => 'active_person']);
        $disabled = User::factory()->disabled()->create(['name' => 'Disabled Person', 'username' => 'disabled_person']);
        $html = $this->actingAs($actor)->get(route('users.index'))->assertOk()
            ->assertSee(['Active Person', 'active_person', 'Disabled Person', 'disabled_person', 'staff', 'Disabled'])
            ->getContent();
        foreach ([$actor, $active, $disabled] as $user) {
            $this->assertStringNotContainsString($user->password, $html);
            $this->assertStringNotContainsString($user->remember_token, $html);
        }
    }

    public function test_name_username_and_wildcard_search_are_literal(): void
    {
        $this->actingAs($this->admin());
        User::factory()->create(['name' => 'Needle Person', 'username' => 'ordinary']);
        User::factory()->create(['name' => 'Other Person', 'username' => 'needle_login']);
        User::factory()->create(['name' => 'Percent% Person', 'username' => 'percent_user']);
        User::factory()->create(['name' => 'Back\\Slash Person', 'username' => 'backslash_user']);
        User::factory()->create(['name' => 'Unrelated Person', 'username' => 'unrelated']);

        $this->get(route('users.index', ['q' => 'Needle']))->assertOk()
            ->assertSee('Needle Person')->assertDontSee('Unrelated Person');
        $this->get(route('users.index', ['q' => 'NEEDLE_LOGIN']))->assertOk()
            ->assertSee('needle_login')->assertDontSee('Unrelated Person');
        $this->get(route('users.index', ['q' => '%']))->assertOk()
            ->assertSee('Percent% Person')->assertDontSee('Unrelated Person');
        $this->get(route('users.index', ['q' => '_']))->assertOk()
            ->assertSee('percent_user')->assertDontSee('Unrelated Person');
        $this->get(route('users.index', ['q' => '!']))->assertOk()->assertDontSee('Unrelated Person');
        $this->get(route('users.index', ['q' => '\\']))->assertOk()
            ->assertSee('Back\\Slash Person')->assertDontSee('Unrelated Person');
    }

    public function test_pagination_is_twenty_stably_ordered_and_preserves_search(): void
    {
        $this->actingAs($this->admin());
        foreach (range(1, 22) as $number) {
            User::factory()->create(['name' => sprintf('Match %02d', $number)]);
        }
        $first = $this->get(route('users.index', ['q' => 'Match']))->assertOk()->viewData('users');
        $second = $this->get(route('users.index', ['q' => 'Match', 'page' => 2]))->assertOk()->viewData('users');
        $this->assertSame(20, $first->count());
        $this->assertSame(2, $second->count());
        $this->assertSame('Match 01', $first->first()->name);
        $this->assertSame('Match 21', $second->first()->name);
        $this->assertStringContainsString('q=Match', $first->nextPageUrl());
    }

    public function test_create_form_is_admin_only_and_defaults_to_staff_without_status_field(): void
    {
        $this->actingAs(User::factory()->create())->get(route('users.create'))->assertForbidden();
        $html = $this->actingAs($this->admin())->get(route('users.create'))->assertOk()
            ->assertSee(['name="name"', 'name="username"', 'name="role"', 'name="password"', 'name="password_confirmation"'], false)
            ->getContent();
        $this->assertStringContainsString('value="staff" selected', $html);
        $this->assertStringNotContainsString('name="status"', $html);
        $this->assertStringNotContainsString('value="disabled"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_creation_defaults_to_active_staff_and_explicit_admin_is_supported(): void
    {
        $actor = $this->admin();
        $this->actingAs($actor)->post(route('users.store'), $this->createData(['username' => '  NEW_USER  ']))
            ->assertRedirect()->assertSessionHas('success');
        $staff = User::query()->where('username', 'new_user')->firstOrFail();
        $this->assertSame('staff', $staff->role);
        $this->assertSame('active', $staff->status);
        $this->assertTrue(Hash::check('valid-password-123', $staff->password));
        $this->assertSame('USER_CREATED', AuditLog::query()->firstOrFail()->action);

        $this->post(route('users.store'), $this->createData(['username' => 'second_admin', 'role' => 'admin']))
            ->assertRedirect();
        $this->assertDatabaseHas('users', ['username' => 'second_admin', 'role' => 'admin', 'status' => 'active']);
    }

    public function test_create_validation_rejects_normalized_duplicate_bad_role_and_password_errors(): void
    {
        $this->actingAs($this->admin());
        User::factory()->create(['username' => 'taken_user']);
        $this->post(route('users.store'), $this->createData(['username' => '  TAKEN_USER  ']))->assertSessionHasErrors('username');
        $this->post(route('users.store'), $this->createData(['role' => 'owner']))->assertSessionHasErrors('role');
        $this->post(route('users.store'), $this->createData(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post(route('users.store'), $this->createData(['password_confirmation' => 'different-secret']))->assertSessionHasErrors('password');
        $this->post(route('users.store'), $this->createData(['status' => 'disabled']))->assertSessionHasErrors('status');
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_profile_update_and_disabled_target_management_use_service(): void
    {
        $actor = $this->admin();
        $target = User::factory()->disabled()->create(['name' => 'Before', 'username' => 'before_user']);
        $this->actingAs($actor)->get(route('users.edit', $target))->assertOk()->assertSee('Before');
        $this->patch(route('users.update', $target), ['name' => 'After', 'username' => '  AFTER_USER  '])
            ->assertRedirect(route('users.edit', $target))->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'After', 'username' => 'after_user', 'status' => 'disabled']);
        $this->assertSame('USER_UPDATED', AuditLog::query()->firstOrFail()->action);
    }

    public function test_profile_update_rejects_other_account_username_and_protected_fields(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();
        $other = User::factory()->create(['username' => 'taken_user']);
        $this->patch(route('users.update', $target), ['name' => 'Changed', 'username' => '  TAKEN_USER  '])
            ->assertSessionHasErrors('username');
        $this->patch(route('users.update', $target), ['name' => 'Changed', 'username' => 'new_username', 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_role_promotion_and_safe_demotion_use_dedicated_event(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($actor)->patch(route('users.role', $target), ['role' => 'admin'])->assertRedirect();
        $this->assertSame('admin', $target->fresh()->role);
        $this->patch(route('users.role', $target), ['role' => 'staff'])->assertRedirect();
        $this->assertSame('staff', $target->fresh()->role);
        $this->assertSame(2, AuditLog::query()->where('action', 'USER_ROLE_CHANGED')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'USER_UPDATED')->count());
    }

    public function test_self_demotion_and_archive_are_controlled_service_errors_even_on_direct_patch(): void
    {
        $actor = $this->admin();
        $this->actingAs($actor)->patch(route('users.role', $actor), ['role' => 'staff'])
            ->assertRedirect()->assertSessionHasErrors('role');
        $this->patch(route('users.archive', $actor))->assertRedirect()->assertSessionHasErrors('status');
        $this->assertDatabaseHas('users', ['id' => $actor->id, 'role' => 'admin', 'status' => 'active']);
        $this->assertSame(0, AuditLog::query()->count());

        $other = $this->admin();
        $this->patch(route('users.role', $actor), ['role' => 'staff'])->assertSessionHasErrors('role');
        $this->patch(route('users.archive', $actor))->assertSessionHasErrors('status');
        $this->assertSame('admin', $actor->fresh()->role);
        $this->assertSame('active', $actor->fresh()->status);
    }

    public function test_own_edit_page_keeps_profile_and_password_forms_but_hides_role_and_archive_actions(): void
    {
        $actor = $this->admin();
        $html = $this->actingAs($actor)->get(route('users.edit', $actor))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('users.update', $actor).'"', $html);
        $this->assertStringContainsString('action="'.route('users.password', $actor).'"', $html);
        $this->assertStringNotContainsString('action="'.route('users.role', $actor).'"', $html);
        $this->assertStringNotContainsString('action="'.route('users.archive', $actor).'"', $html);
        $this->assertStringNotContainsString('value="'.$actor->password.'"', $html);
    }

    public function test_archive_and_reactivate_staff_keep_the_user_row_and_audit_events(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($actor)->patch(route('users.archive', $target))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'disabled']);
        $this->assertSame('USER_DISABLED', AuditLog::query()->firstOrFail()->action);
        $this->patch(route('users.reactivate', $target))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('active', $target->fresh()->status);
        $this->assertSame('USER_REACTIVATED', AuditLog::query()->latest('id')->firstOrFail()->action);
    }

    public function test_password_reset_requires_confirmation_and_keeps_password_private(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($actor)->patch(route('users.password', $target), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->patch(route('users.password', $target), ['password' => 'valid-password-123', 'password_confirmation' => 'different-secret'])
            ->assertSessionHasErrors('password');
        $response = $this->patch(route('users.password', $target), [
            'password' => 'valid-password-123', 'password_confirmation' => 'valid-password-123',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(Hash::check('valid-password-123', $target->fresh()->password));
        $this->assertStringNotContainsString('valid-password-123', json_encode($response->getSession()->all()));
        $log = AuditLog::query()->firstOrFail();
        $this->assertSame('USER_PASSWORD_RESET', $log->action);
        $this->assertNull($log->before_values);
        $this->assertNull($log->after_values);
        $this->assertStringNotContainsString('valid-password-123', json_encode($log->toArray()));

        $this->patch(route('users.password', $actor), [
            'password' => 'self-password-123', 'password_confirmation' => 'self-password-123',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('self-password-123', $actor->fresh()->password));
    }

    public function test_read_pages_do_not_write_users_or_audits_and_never_render_credentials(): void
    {
        $actor = $this->admin();
        $target = User::factory()->disabled()->create();
        $beforeUsers = User::query()->count();
        $this->actingAs($actor);
        foreach ([route('users.index'), route('users.create'), route('users.edit', $target)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString($target->password, $html);
            $this->assertStringNotContainsString($target->remember_token, $html);
        }
        $this->assertSame($beforeUsers, User::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_delete_route_is_absent_and_mutations_are_not_get_routes(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($actor)->delete('/users/'.$target->id)->assertMethodNotAllowed();
        foreach (['users.store', 'users.update', 'users.role', 'users.archive', 'users.reactivate', 'users.password'] as $name) {
            $this->assertNotContains('GET', Route::getRoutes()->getByName($name)->methods());
        }
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User', 'username' => 'new_user',
            'password' => 'valid-password-123', 'password_confirmation' => 'valid-password-123',
        ], $overrides);
    }
}

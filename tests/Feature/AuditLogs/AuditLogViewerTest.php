<?php

namespace Tests\Feature\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Users\UserManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Auth\AuthTestCase;

class AuditLogViewerTest extends AuthTestCase
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

    public function test_route_authorization_methods_and_navigation(): void
    {
        $route = Route::getRoutes()->getByName('audit-logs.index');
        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth', 'active', 'can:access-admin'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }

        $url = route('audit-logs.index');
        $this->get($url)->assertRedirect('/login');
        $staff = User::factory()->create();
        $this->actingAs($staff)->get($url)->assertForbidden();
        $this->actingAs($staff)->get(route('home'))->assertOk()->assertDontSee('data-nav-route="audit-logs.index"', false);
        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get($url)->assertRedirect('/login');
        $this->assertGuest();

        $admin = $this->admin();
        $this->actingAs($admin)->get($url)->assertOk();
        $this->head($url)->assertOk();
        $this->get(route('home'))->assertOk()->assertSee('data-nav-route="audit-logs.index"', false);
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}($url)->assertMethodNotAllowed();
        }
        foreach (['audit-logs.store', 'audit-logs.update', 'audit-logs.destroy', 'audit-logs.export', 'audit-logs.prune'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }
    }

    public function test_each_account_action_has_its_own_row_and_safe_event_summary(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Current Maria', 'username' => 'current_maria']);
        $this->record($admin, 'USER_CREATED', ['entity_id' => $target->id,
            'after_values' => ['name' => 'Maria Original', 'username' => 'maria_original', 'role' => 'staff', 'status' => 'active'],
            'description' => 'User account created.']);
        $this->record($admin, 'USER_UPDATED', ['entity_id' => $target->id,
            'before_values' => ['name' => 'Maria Original', 'username' => 'maria_original'],
            'after_values' => ['name' => 'Current Maria', 'username' => 'current_maria'],
            'description' => 'User profile updated.']);
        $this->record($admin, 'USER_ROLE_CHANGED', ['entity_id' => $target->id,
            'before_values' => ['role' => 'staff'], 'after_values' => ['role' => 'admin'],
            'description' => 'User role changed.']);
        $this->record($admin, 'USER_DISABLED', ['entity_id' => $target->id,
            'before_values' => ['status' => 'active'], 'after_values' => ['status' => 'disabled'],
            'description' => 'User account disabled.']);
        $this->record($admin, 'USER_REACTIVATED', ['entity_id' => $target->id,
            'before_values' => ['status' => 'disabled'], 'after_values' => ['status' => 'active'],
            'description' => 'User account reactivated.']);
        $this->record($admin, 'USER_PASSWORD_RESET', ['entity_id' => $target->id,
            'description' => 'User password reset.']);
        $admin->update(['name' => 'Current Audit Admin', 'username' => 'current_audit_admin']);

        $response = $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
        $html = $response->getContent();
        $this->assertSame(6, substr_count($html, 'data-audit-log-id='));
        $response->assertSee([
            'User Created', 'User Updated', 'User Role Changed', 'User Disabled',
            'User Reactivated', 'User Password Reset', 'User #'.$target->id,
            'Current account: Current Maria', 'Maria Original', 'maria_original',
            'Name:', 'Username:', 'Role:', 'Status:', 'Staff → Admin',
            'Active → Disabled', 'Disabled → Active', 'User password reset.',
            'Current Audit Admin', '@current_audit_admin',
        ]);
        $this->assertSame(6, $response->viewData('entries')->total());
    }

    public function test_unknown_actions_entities_null_entity_and_null_time_render_safely(): void
    {
        $admin = $this->admin();
        $future = $this->record($admin, 'INVENTORY_IMPORT_COMPLETED', [
            'entity_type' => 'import', 'entity_id' => 17,
            'description' => '<script>alert("unsafe")</script>',
        ]);
        $this->record($admin, 'SALE_VOIDED', ['entity_type' => 'sale', 'entity_id' => 23]);
        $system = $this->record($admin, 'SYSTEM_READY', ['entity_type' => null, 'entity_id' => null]);
        DB::table('audit_logs')->where('id', $system->id)->update(['created_at' => null]);

        $response = $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk()
            ->assertSee(['Inventory Import Completed', 'Sale Voided', 'Other record #17', 'Sale #23', 'System event'])
            ->assertSee('<script>alert("unsafe")</script>');
        $this->assertStringNotContainsString('<script>alert("unsafe")</script>', $response->getContent());
        $this->assertStringContainsString('value="INVENTORY_IMPORT_COMPLETED"', $response->getContent());
        $this->assertStringContainsString('value="SALE_VOIDED"', $response->getContent());
        $this->assertStringContainsString('data-audit-log-id="'.$future->id.'"', $response->getContent());
        $this->assertNull($system->fresh()->created_at);
        $this->assertSame('—', trim((string) preg_replace('/\s+/', ' ', $this->timeCell($response->getContent(), $system->id))));
    }

    public function test_snapshot_renderer_ignores_every_unexpected_key_and_value(): void
    {
        $admin = $this->admin();
        $this->record($admin, 'FUTURE_EVENT', [
            'before_values' => [
                'name' => 'Old Safe Name', 'role' => 'staff',
                'password' => 'PRIVATE_PASSWORD_BEFORE', 'password_hash' => 'PRIVATE_HASH_BEFORE',
                'remember_token' => 'PRIVATE_REMEMBER_BEFORE', 'token' => 'PRIVATE_TOKEN_BEFORE',
            ],
            'after_values' => [
                'name' => 'New Safe Name', 'username' => 'safe_username', 'status' => 'active',
                'password' => 'PRIVATE_PASSWORD_AFTER', 'api_token' => 'PRIVATE_API_AFTER',
                'secret' => 'PRIVATE_SECRET_AFTER', 'session' => 'PRIVATE_SESSION_AFTER',
            ],
        ]);

        $html = $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk()
            ->assertSee(['Old Safe Name', 'New Safe Name', 'safe_username', 'Staff', 'Active'])
            ->getContent();
        foreach (['PRIVATE_PASSWORD_BEFORE', 'PRIVATE_HASH_BEFORE', 'PRIVATE_REMEMBER_BEFORE',
            'PRIVATE_TOKEN_BEFORE', 'PRIVATE_PASSWORD_AFTER', 'PRIVATE_API_AFTER',
            'PRIVATE_SECRET_AFTER', 'PRIVATE_SESSION_AFTER', 'password_hash',
            'remember_token', 'api_token', '"password"'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
    }

    public function test_actor_action_and_date_filters_compose_with_and_and_include_disabled_actors(): void
    {
        $admin = $this->admin();
        $actorA = User::factory()->create(['name' => 'Actor Alpha', 'username' => 'actor_alpha']);
        $actorB = User::factory()->disabled()->create(['name' => 'Actor Beta', 'username' => 'actor_beta']);
        $this->record($actorA, 'USER_CREATED', ['description' => 'MATCH_ROW'], '2026-09-26 12:00:00');
        $this->record($actorA, 'USER_UPDATED', ['description' => 'WRONG_ACTION'], '2026-09-26 12:00:00');
        $this->record($actorA, 'USER_CREATED', ['description' => 'WRONG_DATE'], '2026-09-25 12:00:00');
        $this->record($actorB, 'USER_CREATED', ['description' => 'WRONG_ACTOR'], '2026-09-26 12:00:00');
        $this->actingAs($admin);

        $filtered = $this->get(route('audit-logs.index', [
            'user' => $actorA->id, 'action' => 'USER_CREATED',
            'date_from' => '2026-09-26', 'date_to' => '2026-09-26',
        ]))->assertOk()->assertSee('MATCH_ROW')->assertDontSee(['WRONG_ACTION', 'WRONG_DATE', 'WRONG_ACTOR']);
        $this->assertSame(1, $filtered->viewData('entries')->total());
        $this->get(route('audit-logs.index', ['user' => $actorB->id]))->assertOk()
            ->assertSee('WRONG_ACTOR')->assertDontSee('MATCH_ROW')
            ->assertSee('Actor Beta (@actor_beta) — Disabled');
        $this->get(route('audit-logs.index', ['action' => 'USER_UPDATED']))->assertOk()
            ->assertSee('WRONG_ACTION')->assertDontSee('MATCH_ROW');
    }

    public function test_optional_manila_date_bounds_include_ui_dates_and_exclude_adjacent_days(): void
    {
        $admin = $this->admin();
        $this->record($admin, 'USER_CREATED', ['description' => 'BEFORE_DAY'], '2026-09-25 23:59:59');
        $this->record($admin, 'USER_CREATED', ['description' => 'DAY_START'], '2026-09-26 00:00:00');
        $this->record($admin, 'USER_CREATED', ['description' => 'DAY_END'], '2026-09-26 23:59:59');
        $this->record($admin, 'USER_CREATED', ['description' => 'AFTER_DAY'], '2026-09-27 00:00:00');
        $this->actingAs($admin);

        $all = $this->get(route('audit-logs.index'))->assertOk()
            ->assertSee('Sep 26, 2026 12:00 AM');
        $this->assertSame(['AFTER_DAY', 'DAY_END', 'DAY_START', 'BEFORE_DAY'], array_map(
            fn (array $entry): string => $entry['log']->description,
            $all->viewData('entries')->items(),
        ));

        $this->get(route('audit-logs.index', ['date_from' => '2026-09-26']))->assertOk()
            ->assertSee(['DAY_START', 'DAY_END', 'AFTER_DAY'])->assertDontSee('BEFORE_DAY');
        $this->get(route('audit-logs.index', ['date_to' => '2026-09-26']))->assertOk()
            ->assertSee(['BEFORE_DAY', 'DAY_START', 'DAY_END'])->assertDontSee('AFTER_DAY');
        $both = $this->get(route('audit-logs.index', ['date_from' => '2026-09-26', 'date_to' => '2026-09-26']))->assertOk()
            ->assertSee(['DAY_START', 'DAY_END'])->assertDontSee(['BEFORE_DAY', 'AFTER_DAY']);
        $this->assertSame(2, $both->viewData('entries')->total());
    }

    public function test_invalid_filters_fail_closed_with_controlled_errors(): void
    {
        $admin = $this->admin();
        $this->record($admin, 'USER_CREATED', ['description' => 'MUST_NOT_RENDER']);
        $this->actingAs($admin);

        foreach ([
            [['user' => 'nope'], 'Select a valid actor.'],
            [['user' => '999999'], 'Select a valid actor.'],
            [['user' => ['1']], 'Select a valid actor.'],
            [['action' => 'MISSING_EVENT'], 'Select a valid action.'],
            [['action' => ['USER_CREATED']], 'Select a valid action.'],
            [['date_from' => '2026-02-30'], 'Enter a valid date in YYYY-MM-DD format.'],
            [['date_to' => 'not-a-date'], 'Enter a valid date in YYYY-MM-DD format.'],
            [['date_from' => '2026-09-27', 'date_to' => '2026-09-26'], 'The to date must be on or after the from date.'],
        ] as [$filters, $error]) {
            $response = $this->get(route('audit-logs.index', $filters))->assertOk()
                ->assertSee($error)->assertSee('Correct the filter errors to view audit records.')
                ->assertDontSee('MUST_NOT_RENDER');
            $this->assertNull($response->viewData('entries'));
        }
    }

    public function test_newest_order_id_ties_and_twenty_row_pagination_preserve_filters(): void
    {
        $admin = $this->admin();
        for ($number = 1; $number <= 23; $number++) {
            $this->record($admin, 'USER_CREATED', ['description' => 'ROW_'.$number], '2026-09-26 12:00:00');
        }
        $this->record($admin, 'USER_CREATED', ['description' => 'OLDER_ROW'], '2026-09-25 12:00:00');
        $filters = ['user' => $admin->id, 'action' => 'USER_CREATED',
            'date_from' => '2026-09-26', 'date_to' => '2026-09-26'];
        $this->actingAs($admin);

        $first = $this->get(route('audit-logs.index', $filters))->assertOk();
        $page = $first->viewData('entries');
        $this->assertCount(20, $page->items());
        $this->assertSame('ROW_23', $page->items()[0]['log']->description);
        $this->assertSame('ROW_4', $page->items()[19]['log']->description);
        $first->assertDontSee('OLDER_ROW');
        parse_str((string) parse_url($page->url(2), PHP_URL_QUERY), $query);
        foreach ($filters as $key => $value) {
            $this->assertSame((string) $value, (string) $query[$key]);
        }
        $second = $this->get(route('audit-logs.index', array_merge($filters, ['page' => 2])))->assertOk();
        $this->assertSame(['ROW_3', 'ROW_2', 'ROW_1'], array_map(
            fn (array $entry): string => $entry['log']->description,
            $second->viewData('entries')->items(),
        ));
    }

    public function test_empty_states_are_distinct_from_invalid_and_filtered_empty(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk()
            ->assertSee('No audit records have been recorded yet.');
        $this->record($admin, 'USER_CREATED');
        $this->record($admin, 'USER_UPDATED');
        $other = User::factory()->create();
        $this->record($other, 'USER_UPDATED');
        $this->get(route('audit-logs.index', ['user' => $other->id, 'action' => 'USER_CREATED']))->assertOk()
            ->assertSee('No audit records match the selected filters.');
    }

    public function test_index_filtered_get_and_head_are_read_only(): void
    {
        $admin = $this->admin();
        $this->record($admin, 'USER_CREATED');
        $before = [User::query()->count(), AuditLog::query()->count()];
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/\A\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
        $this->get(route('audit-logs.index', ['user' => $admin->id, 'action' => 'USER_CREATED']))->assertOk();
        $this->head(route('audit-logs.index'))->assertOk();
        $this->assertSame([], $writes);
        $this->assertSame($before, [User::query()->count(), AuditLog::query()->count()]);
    }

    public function test_page_queries_remain_bounded_with_many_affected_users(): void
    {
        $admin = $this->admin();
        $firstUser = User::factory()->create();
        $this->record($admin, 'USER_CREATED', ['entity_id' => $firstUser->id]);
        $selects = 0;
        DB::listen(function ($query) use (&$selects): void {
            if (preg_match('/\A\s*SELECT\b/i', $query->sql)) {
                $selects++;
            }
        });
        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
        $singlePageSelects = $selects;

        for ($number = 2; $number <= 20; $number++) {
            $target = User::factory()->create();
            $this->record($admin, 'USER_CREATED', ['entity_id' => $target->id]);
        }
        $before = $selects;
        $this->get(route('audit-logs.index'))->assertOk()->assertSee('User #'.$target->id);
        $fullPageSelects = $selects - $before;
        $this->assertLessThanOrEqual(12, $fullPageSelects);
        $this->assertLessThanOrEqual($singlePageSelects + 2, $fullPageSelects);
    }

    public function test_real_user_management_event_renders_without_a_new_writer(): void
    {
        $admin = $this->admin();
        $created = app(UserManagementService::class)->create($admin, [
            'name' => 'Service Created', 'username' => 'service_created',
            'password' => 'service-secret-123',
        ]);

        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk()
            ->assertSee(['User Created', 'User #'.$created->id, 'Service Created', 'service_created', 'Staff', 'Active'])
            ->assertDontSee('service-secret-123');
        $this->assertSame('USER_CREATED', AuditLog::query()->firstOrFail()->action);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['name' => 'Audit Admin', 'username' => 'audit_admin']);
    }

    private function record(User $actor, string $action, array $attributes = [], ?string $createdAt = null): AuditLog
    {
        $log = new AuditLog(array_merge([
            'user_id' => $actor->id,
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $actor->id,
            'before_values' => null,
            'after_values' => null,
            'description' => 'Safe activity description.',
        ], $attributes));
        if ($createdAt !== null) {
            $log->created_at = CarbonImmutable::parse($createdAt, config('app.timezone'));
        }
        $log->save();

        return $log;
    }

    private function timeCell(string $html, int $id): string
    {
        preg_match('/data-audit-log-id="'.preg_quote((string) $id, '/').'"[^>]*>\s*<td[^>]*>(.*?)<\/td>/s', $html, $matches);

        return strip_tags($matches[1] ?? '');
    }
}

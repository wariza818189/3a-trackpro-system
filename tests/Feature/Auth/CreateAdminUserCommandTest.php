<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserCommandTest extends AuthTestCase
{
    public function test_command_creates_a_normalized_active_admin_with_hashed_password(): void
    {
        $password = 'Test-only-password-42';

        $this->artisan('trackpro:create-admin')
            ->expectsOutputToContain('Target environment: testing')
            ->expectsOutputToContain('Target database: :memory:')
            ->expectsConfirmation('Create a new active administrator on this database?', 'yes')
            ->expectsQuestion('Name', 'Test Administrator')
            ->expectsQuestion('Username', '  INITIAL_ADMIN  ')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();

        $admin = User::query()->sole();
        $this->assertSame('Test Administrator', $admin->name);
        $this->assertSame('initial_admin', $admin->username);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertSame(User::STATUS_ACTIVE, $admin->status);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertNotSame($password, $admin->password);
    }

    public function test_command_requires_explicit_confirmation(): void
    {
        $this->artisan('trackpro:create-admin')
            ->expectsConfirmation('Create a new active administrator on this database?', 'no')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_duplicate_normalized_username_is_refused_without_modifying_existing_user(): void
    {
        $existing = User::factory()->disabled()->create([
            'name' => 'Existing Staff',
            'username' => 'existing_user',
            'password' => 'unchanged-password',
        ]);
        $originalAttributes = $existing->getAttributes();

        $this->artisan('trackpro:create-admin')
            ->expectsConfirmation('Create a new active administrator on this database?', 'yes')
            ->expectsQuestion('Name', 'Replacement Administrator')
            ->expectsQuestion('Username', '  EXISTING_USER  ')
            ->assertFailed();

        $persistedAttributes = $existing->fresh()->getAttributes();
        ksort($originalAttributes);
        ksort($persistedAttributes);

        $this->assertSame(1, User::query()->count());
        $this->assertSame($originalAttributes, $persistedAttributes);
    }

    public function test_mismatched_password_confirmation_is_refused(): void
    {
        $this->artisan('trackpro:create-admin')
            ->expectsConfirmation('Create a new active administrator on this database?', 'yes')
            ->expectsQuestion('Name', 'Test Administrator')
            ->expectsQuestion('Username', 'test_admin')
            ->expectsQuestion('Password', 'Test-only-password-42')
            ->expectsQuestion('Confirm password', 'Different-password-42')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_password_is_not_printed_or_written_to_the_application_log(): void
    {
        $password = 'Never-print-this-42';
        Event::fake([MessageLogged::class]);

        $this->artisan('trackpro:create-admin')
            ->expectsConfirmation('Create a new active administrator on this database?', 'yes')
            ->expectsQuestion('Name', 'Quiet Administrator')
            ->expectsQuestion('Username', 'quiet_admin')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->doesntExpectOutputToContain($password)
            ->assertSuccessful();

        Event::assertNotDispatched(
            MessageLogged::class,
            fn (MessageLogged $event): bool => str_contains(
                $event->message.json_encode($event->context, JSON_THROW_ON_ERROR),
                $password,
            ),
        );
    }
}

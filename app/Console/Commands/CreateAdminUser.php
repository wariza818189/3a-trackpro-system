<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    private const CREATION_FAILED_MESSAGE = 'Administrator was not created. Check the supplied values and target database.';

    protected $signature = 'trackpro:create-admin';

    protected $description = 'Interactively create an active TrackPro administrator';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        $this->components->info('Target environment: '.app()->environment());
        $this->components->info('Target database: '.$database);

        if (! $this->confirm('Create a new active administrator on this database?', false)) {
            $this->components->warn('Administrator creation cancelled.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Name'));
        $username = User::normalizeUsername((string) $this->ask('Username'));

        $identityValidator = Validator::make(
            compact('name', 'username'),
            [
                'name' => ['required', 'string', 'max:120'],
                'username' => ['required', 'string', 'max:50'],
            ],
        );

        if ($identityValidator->fails()) {
            $this->components->error(self::CREATION_FAILED_MESSAGE);

            return self::FAILURE;
        }

        try {
            if (User::query()->where('username', $username)->exists()) {
                $this->components->error(self::CREATION_FAILED_MESSAGE);

                return self::FAILURE;
            }
        } catch (QueryException) {
            $this->components->error(self::CREATION_FAILED_MESSAGE);

            return self::FAILURE;
        }

        $this->line('Password must contain at least 12 characters.');
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        $passwordValidator = Validator::make(
            [
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            ['password' => ['required', 'string', 'min:12', 'confirmed']],
        );

        if ($passwordValidator->fails()) {
            $this->components->error(self::CREATION_FAILED_MESSAGE);

            return self::FAILURE;
        }

        try {
            User::create([
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
            ]);
        } catch (QueryException) {
            $this->components->error(self::CREATION_FAILED_MESSAGE);

            return self::FAILURE;
        }

        $this->components->success('Active administrator created.');

        return self::SUCCESS;
    }
}

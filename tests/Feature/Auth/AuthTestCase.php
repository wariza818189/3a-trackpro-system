<?php

namespace Tests\Feature\Auth;

use LogicException;
use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Authentication tests require the in-memory SQLite database.');
        }

        $this->withoutVite();

        $usersMigration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $usersMigration->up();
    }
}

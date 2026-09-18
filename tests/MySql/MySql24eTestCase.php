<?php

namespace Tests\MySql;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class MySql24eGuardException extends RuntimeException {}

abstract class MySql24eTestCase extends TestCase
{
    protected const CONNECTION = 'mysql_24e_testing';

    protected const DATABASE = 'trackpro_24e_test';

    protected const USERNAME = 'trackpro_24e_test_user';

    protected const ACCOUNT = 'trackpro_24e_test_user@localhost';

    protected const HOST = 'localhost';

    protected const SOCKET = '/var/run/mysqld/mysqld.sock';

    private const PROTECTED_DATABASES = [
        'trackpro_local',
        'trackpro_test',
        'trackpro_ft17_test',
    ];

    /** @var list<string> */
    private const EXPECTED_SCHEMA_PRIVILEGES = [
        'ALTER',
        'CREATE',
        'DELETE',
        'DROP',
        'INDEX',
        'INSERT',
        'REFERENCES',
        'SELECT',
        'UPDATE',
    ];

    private string $previousDefaultConnection;

    private object $verifiedIdentity;

    /** @var list<array{string, string, string}> */
    private array $verifiedSchemaPrivileges;

    /** @var list<array{string, string}> */
    private array $verifiedGlobalPrivileges;

    private int $verifiedRoutinePrivilegeCount;

    private string $verifiedConnectionStatus;

    /** @var list<string> */
    private array $verifiedGrantForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');
        $configuration = $this->runtimeConfiguration();
        $this->guardRuntimeConfiguration(self::CONNECTION, $configuration);

        config()->set('database.connections.'.self::CONNECTION, $configuration);
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        try {
            $this->safelyGuardMySql24eConnection();
        } catch (Throwable $exception) {
            DB::disconnect(self::CONNECTION);
            DB::purge(self::CONNECTION);
            DB::setDefaultConnection($this->previousDefaultConnection);
            config()->offsetUnset('database.connections.'.self::CONNECTION);

            if ($exception instanceof MySql24eGuardException) {
                throw $exception;
            }

            throw new RuntimeException('The dedicated #24E MySQL identity guard failed safely.');
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect(self::CONNECTION);
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection($this->previousDefaultConnection);
        config()->offsetUnset('database.connections.'.self::CONNECTION);

        parent::tearDown();
    }

    protected function guardedMySql24eConnection(): Connection
    {
        return $this->safelyGuardMySql24eConnection();
    }

    protected function verifiedIdentity(): object
    {
        return $this->verifiedIdentity;
    }

    /** @return list<array{string, string, string}> */
    protected function verifiedSchemaPrivileges(): array
    {
        return $this->verifiedSchemaPrivileges;
    }

    /** @return list<array{string, string}> */
    protected function verifiedGlobalPrivileges(): array
    {
        return $this->verifiedGlobalPrivileges;
    }

    protected function verifiedRoutinePrivilegeCount(): int
    {
        return $this->verifiedRoutinePrivilegeCount;
    }

    protected function verifiedConnectionStatus(): string
    {
        return $this->verifiedConnectionStatus;
    }

    /** @return list<string> */
    protected function verifiedGrantForms(): array
    {
        return $this->verifiedGrantForms;
    }

    /** @return array<string, mixed> */
    private function runtimeConfiguration(): array
    {
        return [
            'driver' => 'mysql',
            'host' => $this->environmentValue('TRACKPRO_24E_DB_HOST'),
            'port' => $this->environmentValue('TRACKPRO_24E_DB_PORT'),
            'database' => $this->environmentValue('TRACKPRO_24E_DB_DATABASE'),
            'username' => $this->environmentValue('TRACKPRO_24E_DB_USERNAME'),
            'password' => $this->environmentValue('TRACKPRO_24E_DB_PASSWORD'),
            'unix_socket' => $this->environmentValue('TRACKPRO_24E_DB_SOCKET'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [],
        ];
    }

    private function environmentValue(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $configuration */
    private function guardRuntimeConfiguration(string $connectionName, array $configuration): void
    {
        $database = $configuration['database'] ?? null;
        $port = filter_var(
            $configuration['port'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]],
        );

        if (! app()->environment('testing')
            || $connectionName !== self::CONNECTION
            || ($configuration['driver'] ?? null) !== 'mysql'
            || ! is_string($database)
            || $database === ''
            || in_array($database, self::PROTECTED_DATABASES, true)
            || $database !== self::DATABASE
            || ($configuration['username'] ?? null) !== self::USERNAME
            || ($configuration['password'] ?? '') === ''
            || ($configuration['host'] ?? null) !== self::HOST
            || ($configuration['unix_socket'] ?? null) !== self::SOCKET
            || $port === false) {
            throw new MySql24eGuardException('The dedicated #24E MySQL configuration is not exact and isolated.');
        }
    }

    private function safelyGuardMySql24eConnection(): Connection
    {
        try {
            return $this->guardMySql24eConnection();
        } catch (MySql24eGuardException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MySql24eGuardException('The dedicated #24E MySQL identity guard failed safely.');
        }
    }

    private function guardMySql24eConnection(): Connection
    {
        $configuration = config('database.connections.'.self::CONNECTION);
        if (! is_array($configuration)) {
            throw new MySql24eGuardException('The dedicated #24E MySQL connection is not configured.');
        }
        $this->guardRuntimeConfiguration((string) config('database.default'), $configuration);

        $connection = DB::connection(self::CONNECTION);
        $identity = $connection->selectOne(
            'SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name, '
            .'@@socket AS socket_path, @@default_storage_engine AS default_engine, VERSION() AS version'
        );

        if ($identity === null
            || $identity->database_name !== self::DATABASE
            || $identity->account_name !== self::ACCOUNT
            || $identity->socket_path !== self::SOCKET
            || strcasecmp((string) $identity->default_engine, 'InnoDB') !== 0
            || preg_match('/\A8\.\d+(?:\.\d+)?/', (string) $identity->version) !== 1) {
            throw new MySql24eGuardException('The live #24E MySQL server identity is not exact.');
        }

        $connectionStatus = (string) $connection->getPdo()->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        if (! str_contains(strtolower($connectionStatus), 'unix socket')) {
            throw new MySql24eGuardException('The #24E MySQL connection is not using the required Unix socket.');
        }

        try {
            $this->guardPrivilegeScope($connection);
        } catch (MySql24eGuardException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MySql24eGuardException('The #24E MySQL privilege metadata could not be verified safely.');
        }
        $this->verifiedIdentity = $identity;
        $this->verifiedConnectionStatus = $connectionStatus;

        return $connection;
    }

    private function guardPrivilegeScope(Connection $connection): void
    {
        $grantee = "'".self::USERNAME."'@'localhost'";
        $partialRevokes = (int) $connection->scalar('SELECT @@partial_revokes');
        if (! in_array($partialRevokes, [0, 1], true)) {
            throw new MySql24eGuardException('The #24E MySQL partial-revokes state is invalid.');
        }
        $expectedScope = $partialRevokes === 1
            ? self::DATABASE
            : str_replace('_', '\\_', self::DATABASE);

        $schemaPrivileges = array_map(
            static fn (object $row): array => [
                (string) $row->scope,
                (string) $row->privilege,
                (string) $row->is_grantable,
            ],
            $connection->select(
                'SELECT TABLE_SCHEMA AS scope, PRIVILEGE_TYPE AS privilege, IS_GRANTABLE AS is_grantable '
                .'FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE = ? ORDER BY PRIVILEGE_TYPE',
                [$grantee],
            ),
        );
        $expectedSchemaPrivileges = array_map(
            static fn (string $privilege): array => [$expectedScope, $privilege, 'NO'],
            self::EXPECTED_SCHEMA_PRIVILEGES,
        );
        if (count($schemaPrivileges) !== count($expectedSchemaPrivileges)) {
            throw new MySql24eGuardException('The #24E MySQL schema privilege count is not exact.');
        }
        if (array_column($schemaPrivileges, 0) !== array_column($expectedSchemaPrivileges, 0)) {
            throw new MySql24eGuardException('The #24E MySQL schema privilege database scope is not exact.');
        }
        if (array_column($schemaPrivileges, 1) !== self::EXPECTED_SCHEMA_PRIVILEGES) {
            throw new MySql24eGuardException('The #24E MySQL schema privilege names are not exact.');
        }
        if (array_unique(array_column($schemaPrivileges, 2)) !== ['NO']) {
            throw new MySql24eGuardException('The #24E MySQL schema privileges unexpectedly include grantability.');
        }

        $globalPrivileges = array_map(
            static fn (object $row): array => [(string) $row->privilege, (string) $row->is_grantable],
            $connection->select(
                'SELECT PRIVILEGE_TYPE AS privilege, IS_GRANTABLE AS is_grantable '
                .'FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = ? ORDER BY PRIVILEGE_TYPE',
                [$grantee],
            ),
        );
        if ($globalPrivileges !== [['USAGE', 'NO']]) {
            throw new MySql24eGuardException('The #24E MySQL account has an unexpected global privilege.');
        }

        try {
            $routinePrivilegeCount = (int) $connection->scalar(
                'SELECT COUNT(*) FROM information_schema.ROLE_ROUTINE_GRANTS '
                .'WHERE GRANTEE = ? AND GRANTEE_HOST = ?',
                [self::USERNAME, 'localhost'],
            );
        } catch (Throwable) {
            throw new MySql24eGuardException('The #24E MySQL routine privilege metadata could not be verified safely.');
        }
        if ($routinePrivilegeCount !== 0) {
            throw new MySql24eGuardException('The #24E MySQL account has an unexpected routine privilege.');
        }

        $otherPrivilegeCount = (int) $connection->scalar(
            'SELECT '
            .'(SELECT COUNT(*) FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE = ?) + '
            .'(SELECT COUNT(*) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE = ?) + '
            .'(SELECT COUNT(*) FROM information_schema.APPLICABLE_ROLES '
            .'WHERE GRANTEE = ? AND GRANTEE_HOST = ?)',
            [$grantee, $grantee, self::USERNAME, 'localhost'],
        );
        if ($otherPrivilegeCount !== 0) {
            throw new MySql24eGuardException('The #24E MySQL account has an unexpected table, column, or role privilege.');
        }

        $grants = array_map(
            static fn (object $row): string => (string) array_values((array) $row)[0],
            $connection->select('SHOW GRANTS FOR CURRENT_USER()'),
        );
        try {
            $grantForms = $this->guardShowGrants($grants, $partialRevokes);
        } catch (MySql24eGuardException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MySql24eGuardException('The #24E MySQL grant statements could not be verified safely.');
        }

        $this->verifiedSchemaPrivileges = array_map(
            static fn (array $privilege): array => [self::DATABASE, $privilege[1], $privilege[2]],
            $schemaPrivileges,
        );
        $this->verifiedGlobalPrivileges = $globalPrivileges;
        $this->verifiedRoutinePrivilegeCount = $routinePrivilegeCount;
        $this->verifiedGrantForms = $grantForms;
    }

    /**
     * @param  list<string>  $grants
     * @return list<string>
     */
    private function guardShowGrants(array $grants, int $partialRevokes): array
    {
        if (count($grants) !== 2) {
            throw new MySql24eGuardException('The #24E MySQL grant statement count is not exact.');
        }

        $expectedAccount = '`'.self::USERNAME.'`@`localhost`';
        $schemaScope = $partialRevokes === 1
            ? self::DATABASE
            : str_replace('_', '\\_', self::DATABASE);
        $expectedSchemaScope = '`'.$schemaScope.'`.*';
        $expectedSchemaPrivileges = self::EXPECTED_SCHEMA_PRIVILEGES;
        sort($expectedSchemaPrivileges);
        $forms = [];

        foreach ($grants as $grant) {
            $normalizedGrant = preg_replace('/\s+/', ' ', trim($grant));
            if (! is_string($normalizedGrant)
                || preg_match(
                    '/\AGRANT (?<privileges>.+?) ON (?<scope>\S+) TO (?<account>\S+)\z/i',
                    $normalizedGrant,
                    $matches,
                ) !== 1
                || $matches['account'] !== $expectedAccount) {
                throw new MySql24eGuardException('The #24E MySQL grant statement is not explicitly permitted.');
            }

            $privileges = array_map(
                static fn (string $privilege): string => strtoupper(trim($privilege)),
                explode(',', $matches['privileges']),
            );
            sort($privileges);

            if ($matches['scope'] === '*.*' && $privileges === ['USAGE']) {
                $forms[] = 'global_usage';

                continue;
            }

            if ($matches['scope'] === $expectedSchemaScope
                && $privileges === $expectedSchemaPrivileges) {
                $forms[] = 'schema_privileges';

                continue;
            }

            throw new MySql24eGuardException('The #24E MySQL grant statement is not explicitly permitted.');
        }

        sort($forms);
        if ($forms !== ['global_usage', 'schema_privileges']) {
            throw new MySql24eGuardException('The #24E MySQL grant forms are not exact.');
        }

        return $forms;
    }
}

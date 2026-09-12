<?php

declare(strict_types=1);

namespace TrackPro\Ft17Support;

use RuntimeException;

final class SafetyGuard
{
    public const ACCOUNT = 'trackpro_ft17_test_user@localhost';

    public const DATABASE = 'trackpro_ft17_test';

    public const CONNECTION = 'mysql_ft17_testing';

    public const MODE = 'edge-permission-testing';

    public const SOCKET = '/var/run/mysqld/mysqld.sock';

    public const USERNAME = 'trackpro_ft17_test_user';

    /** @var list<string> */
    public const EXPECTED_TABLES = [
        'audit_logs',
        'categories',
        'migrations',
        'product_variants',
        'products',
        'restock_items',
        'restocks',
        'sale_items',
        'sales',
        'stock_movements',
        'users',
    ];

    /** @var list<string> */
    private const PROTECTED_DATABASES = ['trackpro_local', 'trackpro_test'];

    public static function assertMode(mixed $mode): void
    {
        if ($mode !== self::MODE) {
            throw new RuntimeException('FT17_MODE must explicitly equal edge-permission-testing.');
        }
    }

    /** @param array<string, mixed> $connection */
    public static function assertConfiguration(
        string $environment,
        mixed $defaultConnection,
        array $connection,
    ): void {
        $database = $connection['database'] ?? null;

        if ($environment !== 'testing'
            || $defaultConnection !== self::CONNECTION
            || ($connection['driver'] ?? null) !== 'mysql'
            || $database !== self::DATABASE
            || in_array($database, self::PROTECTED_DATABASES, true)
            || ($connection['username'] ?? null) !== self::USERNAME
            || ($connection['unix_socket'] ?? null) !== self::SOCKET
            || ! in_array($connection['host'] ?? null, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('Live access refused: the Laravel connection is not the reserved local FT17 target.');
        }
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array{database: string, account: string, driver: string, engine: string, mysql_major: string}
     */
    public static function assertLiveIdentity(
        array $identity,
        string $driverName,
        string $connectionStatus,
    ): array {
        $database = $identity['database_name'] ?? null;

        if ($driverName !== 'mysql'
            || $database !== self::DATABASE
            || in_array($database, self::PROTECTED_DATABASES, true)
            || ($identity['account_name'] ?? null) !== self::ACCOUNT
            || ($identity['socket_path'] ?? null) !== self::SOCKET
            || strcasecmp((string) ($identity['default_engine'] ?? ''), 'InnoDB') !== 0
            || preg_match('/\A8\./', (string) ($identity['version'] ?? '')) !== 1
            || ! str_contains(strtolower($connectionStatus), 'unix socket')) {
            throw new RuntimeException('Live access refused: the server-side FT17 MySQL identity could not be proven.');
        }

        return [
            'database' => self::DATABASE,
            'account' => self::ACCOUNT,
            'driver' => 'mysql',
            'engine' => 'InnoDB',
            'mysql_major' => '8',
        ];
    }

    /**
     * @param  list<array{string, string, string}>  $schemaPrivileges
     * @param  list<array{string, string}>  $userPrivileges
     */
    public static function assertAccountPrivileges(
        int $partialRevokes,
        array $schemaPrivileges,
        array $userPrivileges,
        int $otherPrivilegeCount,
    ): void {
        if (! in_array($partialRevokes, [0, 1], true)) {
            throw new RuntimeException('Live access refused: the MySQL partial-revokes mode is unexpected.');
        }

        $scope = $partialRevokes === 1
            ? strtoupper(bin2hex(self::DATABASE))
            : strtoupper(bin2hex(str_replace('_', '\\_', self::DATABASE)));
        $expectedSchemaPrivileges = array_map(
            static fn (string $privilege): array => [$scope, $privilege, 'NO'],
            ['ALTER', 'CREATE', 'DELETE', 'DROP', 'INDEX', 'INSERT', 'REFERENCES', 'SELECT', 'UPDATE'],
        );

        if ($schemaPrivileges !== $expectedSchemaPrivileges
            || $userPrivileges !== [['USAGE', 'NO']]
            || $otherPrivilegeCount !== 0) {
            throw new RuntimeException('Live access refused: the FT17 account grants are not limited to the isolated database.');
        }
    }

    /** @param list<string> $tables */
    public static function assertMigratedSchema(array $tables): void
    {
        if ($tables !== self::EXPECTED_TABLES) {
            throw new RuntimeException('Fixture writes refused: the freshly migrated FT17 schema inventory is unexpected.');
        }
    }
}

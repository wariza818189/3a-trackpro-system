<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TrackPro\Ft17Support\SafetyGuard;

require_once dirname(__DIR__, 3).'/scripts/ft17/SafetyGuard.php';

final class Ft17SafetyGuardTest extends TestCase
{
    private const ACCOUNT_GRANTEE = '`trackpro_ft17_test_user`@`localhost`';

    /** @var list<string> */
    private const REQUIRED_PRIVILEGES = [
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

    #[Test]
    public function it_accepts_only_the_exact_mode_configuration_and_live_identity(): void
    {
        SafetyGuard::assertMode('edge-permission-testing');
        SafetyGuard::assertConfiguration('testing', SafetyGuard::CONNECTION, $this->configuration());

        $result = SafetyGuard::assertLiveIdentity($this->identity(), 'mysql', 'Localhost via UNIX socket');

        $this->assertSame(SafetyGuard::DATABASE, $result['database']);
        $this->assertSame(SafetyGuard::ACCOUNT, $result['account']);
    }

    #[Test]
    public function it_accepts_a_wildcard_safe_schema_grant_when_partial_revokes_are_disabled(): void
    {
        $privilegesInDifferentOrder = array_reverse(self::REQUIRED_PRIVILEGES);

        SafetyGuard::assertAccountPrivileges(
            0,
            self::schemaPrivileges(),
            [['USAGE', 'NO']],
            0,
            self::showGrants(0, $privilegesInDifferentOrder),
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_rejects_an_unescaped_wildcard_schema_grant_when_partial_revokes_are_disabled(): void
    {
        $this->expectException(RuntimeException::class);

        SafetyGuard::assertAccountPrivileges(
            0,
            self::schemaPrivileges(),
            [['USAGE', 'NO']],
            0,
            self::showGrants(0, scope: '`trackpro_ft17_test`.*'),
        );
    }

    #[Test]
    public function it_accepts_a_literal_schema_grant_when_partial_revokes_are_enabled(): void
    {
        $showGrants = array_reverse(self::showGrants(1));

        SafetyGuard::assertAccountPrivileges(1, self::schemaPrivileges(), [['USAGE', 'NO']], 0, $showGrants);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function schema_privilege_metadata_always_uses_the_literal_logical_database_name(): void
    {
        $escapedMetadata = self::schemaPrivileges();
        foreach ($escapedMetadata as &$privilege) {
            $privilege[0] = 'trackpro\\_ft17\\_test';
        }
        unset($privilege);

        $this->expectException(RuntimeException::class);

        SafetyGuard::assertAccountPrivileges(0, $escapedMetadata, [['USAGE', 'NO']], 0, self::showGrants(0));
    }

    #[Test]
    #[DataProvider('unsafeShowGrantsProvider')]
    public function it_rejects_every_unexpected_show_grants_posture(int $partialRevokes, array $showGrants): void
    {
        $this->expectException(RuntimeException::class);

        SafetyGuard::assertAccountPrivileges(
            $partialRevokes,
            self::schemaPrivileges(),
            [['USAGE', 'NO']],
            0,
            $showGrants,
        );
    }

    public static function unsafeShowGrantsProvider(): array
    {
        $account = self::ACCOUNT_GRANTEE;
        $schemaPrivileges = implode(', ', self::REQUIRED_PRIVILEGES);
        $valid = self::showGrants(0);

        return [
            'wrong database grant' => [0, self::showGrants(0, scope: '`unrelated\\_database`.*')],
            'protected local database grant' => [0, self::showGrants(0, scope: '`trackpro\\_local`.*')],
            'protected frozen database grant' => [0, self::showGrants(0, scope: '`trackpro\\_test`.*')],
            'extra global privilege' => [0, ["GRANT SELECT ON *.* TO {$account}", $valid[1]]],
            'grant option' => [0, [$valid[0], $valid[1].' WITH GRANT OPTION']],
            'table-specific grant' => [0, [$valid[0], "GRANT SELECT ON `trackpro_ft17_test`.`products` TO {$account}"]],
            'column-specific grant' => [0, [$valid[0], "GRANT SELECT (`name`) ON `trackpro_ft17_test`.`products` TO {$account}"]],
            'procedure grant' => [0, [$valid[0], "GRANT EXECUTE ON PROCEDURE `trackpro_ft17_test`.`unsafe` TO {$account}"]],
            'function grant' => [0, [$valid[0], "GRANT EXECUTE ON FUNCTION `trackpro_ft17_test`.`unsafe` TO {$account}"]],
            'granted role' => [0, [$valid[0], "GRANT `unexpected_role`@`localhost` TO {$account}"]],
            'partial restriction statement' => [0, [$valid[0], "REVOKE INSERT ON *.* FROM {$account}"]],
            'extra unrelated statement' => [0, [...$valid, "SET DEFAULT ROLE ALL TO {$account}"]],
            'missing privilege' => [0, self::showGrants(0, array_slice(self::REQUIRED_PRIVILEGES, 1))],
            'extra privilege' => [0, self::showGrants(0, [...self::REQUIRED_PRIVILEGES, 'CREATE VIEW'])],
            'escaped schema with partial revokes enabled' => [1, self::showGrants(1, scope: '`trackpro\\_ft17\\_test`.*')],
            'duplicate database statement' => [0, [$valid[1], $valid[1]]],
            'wrong grantee account' => [0, [
                $valid[0],
                "GRANT {$schemaPrivileges} ON `trackpro\\_ft17\\_test`.* TO `trackpro_test_user`@`localhost`",
            ]],
        ];
    }

    #[Test]
    #[DataProvider('unsafeGrantProvider')]
    public function it_fails_closed_for_missing_global_grantable_or_role_expanded_privileges(
        int $partialRevokes,
        array $schemaPrivileges,
        array $userPrivileges,
        int $otherPrivilegeCount,
    ): void {
        $this->expectException(RuntimeException::class);

        SafetyGuard::assertAccountPrivileges(
            $partialRevokes,
            $schemaPrivileges,
            $userPrivileges,
            $otherPrivilegeCount,
            self::showGrants(in_array($partialRevokes, [0, 1], true) ? $partialRevokes : 0),
        );
    }

    public static function unsafeGrantProvider(): array
    {
        $missingSchemaPrivilege = self::schemaPrivileges();
        array_pop($missingSchemaPrivilege);
        $grantablePrivilege = self::schemaPrivileges();
        $grantablePrivilege[0][2] = 'YES';
        $unrelatedScope = self::schemaPrivileges();
        $unrelatedScope[0][0] = 'trackpro_local';

        return [
            'unexpected partial revokes mode' => [2, self::schemaPrivileges(), [['USAGE', 'NO']], 0],
            'missing schema privilege' => [0, $missingSchemaPrivilege, [['USAGE', 'NO']], 0],
            'unrelated metadata schema scope' => [0, $unrelatedScope, [['USAGE', 'NO']], 0],
            'global select privilege' => [0, self::schemaPrivileges(), [['SELECT', 'NO'], ['USAGE', 'NO']], 0],
            'grant option' => [0, $grantablePrivilege, [['USAGE', 'NO']], 0],
            'table or column grant' => [0, self::schemaPrivileges(), [['USAGE', 'NO']], 1],
        ];
    }

    #[Test]
    public function it_rejects_an_unexpected_migrated_schema_inventory(): void
    {
        $this->expectException(RuntimeException::class);

        SafetyGuard::assertMigratedSchema(array_slice(SafetyGuard::EXPECTED_TABLES, 1));
    }

    #[Test]
    public function it_rejects_a_missing_or_incorrect_mode(): void
    {
        foreach ([null, '', 'functional-testing', 'edge_permission_testing'] as $mode) {
            try {
                SafetyGuard::assertMode($mode);
                $this->fail('The incorrect FT17 mode was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('FT17_MODE', $exception->getMessage());
            }
        }
    }

    #[Test]
    #[DataProvider('unsafeConfigurationProvider')]
    public function it_rejects_every_mismatched_laravel_target(string $environment, string $default, array $configuration): void
    {
        $this->expectException(RuntimeException::class);

        SafetyGuard::assertConfiguration($environment, $default, $configuration);
    }

    public static function unsafeConfigurationProvider(): array
    {
        $valid = self::validConfiguration();

        return [
            'non-testing environment' => ['local', SafetyGuard::CONNECTION, $valid],
            'default mysql connection' => ['testing', 'mysql', $valid],
            'default frozen testing connection' => ['testing', 'mysql_testing', $valid],
            'wrong driver' => ['testing', SafetyGuard::CONNECTION, array_replace($valid, ['driver' => 'sqlite'])],
            'missing database' => ['testing', SafetyGuard::CONNECTION, array_replace($valid, ['database' => null])],
            'frozen FT15 database' => ['testing', SafetyGuard::CONNECTION, array_replace($valid, ['database' => 'trackpro_test'])],
            'protected local database' => ['testing', SafetyGuard::CONNECTION, array_replace($valid, ['database' => 'trackpro_local'])],
            'wrong account' => ['testing', SafetyGuard::CONNECTION, array_replace($valid, ['username' => 'trackpro_test_user'])],
            'wrong socket' => ['testing', SafetyGuard::CONNECTION, array_replace($valid, ['unix_socket' => '/tmp/mysql.sock'])],
            'remote host' => ['testing', SafetyGuard::CONNECTION, array_replace($valid, ['host' => 'db.example.test'])],
        ];
    }

    #[Test]
    #[DataProvider('unsafeLiveIdentityProvider')]
    public function it_rejects_every_mismatched_server_side_identity(array $identity, string $driver, string $status): void
    {
        $this->expectException(RuntimeException::class);

        SafetyGuard::assertLiveIdentity($identity, $driver, $status);
    }

    public static function unsafeLiveIdentityProvider(): array
    {
        $valid = self::validIdentity();

        return [
            'protected local database' => [array_replace($valid, ['database_name' => 'trackpro_local']), 'mysql', 'via UNIX socket'],
            'frozen FT15 database' => [array_replace($valid, ['database_name' => 'trackpro_test']), 'mysql', 'via UNIX socket'],
            'wrong account' => [array_replace($valid, ['account_name' => 'trackpro_test_user@localhost']), 'mysql', 'via UNIX socket'],
            'wrong driver' => [$valid, 'sqlite', 'via UNIX socket'],
            'wrong socket' => [array_replace($valid, ['socket_path' => '/tmp/mysql.sock']), 'mysql', 'via UNIX socket'],
            'tcp connection' => [$valid, 'mysql', '127.0.0.1 via TCP/IP'],
            'wrong engine' => [array_replace($valid, ['default_engine' => 'MyISAM']), 'mysql', 'via UNIX socket'],
            'wrong major version' => [array_replace($valid, ['version' => '9.0.1']), 'mysql', 'via UNIX socket'],
        ];
    }

    private function configuration(): array
    {
        return self::validConfiguration();
    }

    private function identity(): array
    {
        return self::validIdentity();
    }

    private static function validConfiguration(): array
    {
        return [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => SafetyGuard::DATABASE,
            'username' => SafetyGuard::USERNAME,
            'unix_socket' => SafetyGuard::SOCKET,
        ];
    }

    private static function validIdentity(): array
    {
        return [
            'database_name' => SafetyGuard::DATABASE,
            'account_name' => SafetyGuard::ACCOUNT,
            'socket_path' => SafetyGuard::SOCKET,
            'default_engine' => 'InnoDB',
            'version' => '8.0.46',
        ];
    }

    private static function schemaPrivileges(): array
    {
        return array_map(
            static fn (string $privilege): array => [SafetyGuard::DATABASE, $privilege, 'NO'],
            self::REQUIRED_PRIVILEGES,
        );
    }

    /**
     * @param  list<string>|null  $privileges
     * @return list<string>
     */
    private static function showGrants(int $partialRevokes, ?array $privileges = null, ?string $scope = null): array
    {
        $scope ??= $partialRevokes === 0
            ? '`trackpro\\_ft17\\_test`.*'
            : '`trackpro_ft17_test`.*';
        $privileges ??= self::REQUIRED_PRIVILEGES;

        return [
            'GRANT USAGE ON *.* TO '.self::ACCOUNT_GRANTEE,
            'GRANT '.implode(', ', $privileges).' ON '.$scope.' TO '.self::ACCOUNT_GRANTEE,
        ];
    }
}

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
    #[Test]
    public function it_accepts_only_the_exact_mode_configuration_and_live_identity(): void
    {
        SafetyGuard::assertMode('edge-permission-testing');
        SafetyGuard::assertConfiguration('testing', SafetyGuard::CONNECTION, $this->configuration());

        $result = SafetyGuard::assertLiveIdentity($this->identity(), 'mysql', 'Localhost via UNIX socket');
        SafetyGuard::assertAccountPrivileges(0, self::schemaPrivileges(false), [['USAGE', 'NO']], 0);
        SafetyGuard::assertAccountPrivileges(1, self::schemaPrivileges(true), [['USAGE', 'NO']], 0);

        $this->assertSame(SafetyGuard::DATABASE, $result['database']);
        $this->assertSame(SafetyGuard::ACCOUNT, $result['account']);
    }

    #[Test]
    public function it_rejects_account_grants_outside_the_exact_ft17_schema_scope(): void
    {
        $unsafe = self::schemaPrivileges(false);
        $unsafe[] = ['747261636B70726F5F6C6F63616C', 'SELECT', 'NO'];

        $this->expectException(RuntimeException::class);

        SafetyGuard::assertAccountPrivileges(0, $unsafe, [['USAGE', 'NO']], 0);
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
        );
    }

    public static function unsafeGrantProvider(): array
    {
        $missingSchemaPrivilege = self::schemaPrivileges(false);
        array_pop($missingSchemaPrivilege);
        $grantablePrivilege = self::schemaPrivileges(false);
        $grantablePrivilege[0][2] = 'YES';

        return [
            'unexpected partial revokes mode' => [2, self::schemaPrivileges(false), [['USAGE', 'NO']], 0],
            'missing schema privilege' => [0, $missingSchemaPrivilege, [['USAGE', 'NO']], 0],
            'global select privilege' => [0, self::schemaPrivileges(false), [['SELECT', 'NO'], ['USAGE', 'NO']], 0],
            'grant option' => [0, $grantablePrivilege, [['USAGE', 'NO']], 0],
            'table column or role grant' => [0, self::schemaPrivileges(false), [['USAGE', 'NO']], 1],
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

    private static function schemaPrivileges(bool $partialRevokes): array
    {
        $scope = $partialRevokes
            ? strtoupper(bin2hex(SafetyGuard::DATABASE))
            : strtoupper(bin2hex(str_replace('_', '\\_', SafetyGuard::DATABASE)));

        return array_map(
            static fn (string $privilege): array => [$scope, $privilege, 'NO'],
            ['ALTER', 'CREATE', 'DELETE', 'DROP', 'INDEX', 'INSERT', 'REFERENCES', 'SELECT', 'UPDATE'],
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Ft17\MySql;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;
use TrackPro\Ft17Support\SafetyGuard;

require_once dirname(__DIR__, 3).'/scripts/ft17/SafetyGuard.php';

final class DatabaseIdentityTest extends TestCase
{
    public function test_live_identity_and_migrated_schema_are_the_reserved_ft17_target(): void
    {
        SafetyGuard::assertMode(getenv('FT17_MODE'));
        $configuration = config('database.connections.'.SafetyGuard::CONNECTION);
        SafetyGuard::assertConfiguration(
            app()->environment(),
            config('database.default'),
            is_array($configuration) ? $configuration : [],
        );

        $connection = DB::connection(SafetyGuard::CONNECTION);
        $pdo = $connection->getPdo();
        $identity = $connection->selectOne(
            'SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name, '
            .'@@socket AS socket_path, @@default_storage_engine AS default_engine, VERSION() AS version'
        );
        $verified = SafetyGuard::assertLiveIdentity(
            $identity === null ? [] : (array) $identity,
            (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            (string) $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS),
        );
        $this->assertAccountPrivilegesAreIsolated($connection);
        $tables = array_map(
            static fn (object $row): string => (string) $row->table_name,
            $connection->select(
                'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES '
                .'WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
                [SafetyGuard::DATABASE, 'BASE TABLE'],
            ),
        );
        SafetyGuard::assertMigratedSchema($tables);

        $this->assertSame(SafetyGuard::DATABASE, $verified['database']);
        $this->assertSame(SafetyGuard::ACCOUNT, $verified['account']);
        $this->assertSame(SafetyGuard::EXPECTED_TABLES, $tables);
    }

    private function assertAccountPrivilegesAreIsolated(Connection $connection): void
    {
        $grantee = "CONCAT(QUOTE('trackpro_ft17_test_user'), '@', QUOTE('localhost'))";
        $schemaPrivileges = array_map(
            static fn (object $row): array => [(string) $row->scope_hex, (string) $row->privilege, (string) $row->is_grantable],
            $connection->select(
                'SELECT HEX(TABLE_SCHEMA) AS scope_hex, PRIVILEGE_TYPE AS privilege, IS_GRANTABLE AS is_grantable '
                .'FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE = '.$grantee.' ORDER BY PRIVILEGE_TYPE'
            ),
        );
        $userPrivileges = array_map(
            static fn (object $row): array => [(string) $row->privilege, (string) $row->is_grantable],
            $connection->select(
                'SELECT PRIVILEGE_TYPE AS privilege, IS_GRANTABLE AS is_grantable '
                .'FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = '.$grantee.' ORDER BY PRIVILEGE_TYPE'
            ),
        );
        $otherPrivilegeCount = (int) $connection->scalar(
            'SELECT '
            .'(SELECT COUNT(*) FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE = '.$grantee.') + '
            .'(SELECT COUNT(*) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE = '.$grantee.') + '
            .'(SELECT COUNT(*) FROM information_schema.ROUTINE_PRIVILEGES WHERE GRANTEE = '.$grantee.') + '
            ."(SELECT COUNT(*) FROM information_schema.APPLICABLE_ROLES WHERE GRANTEE = 'trackpro_ft17_test_user' "
            ."AND GRANTEE_HOST = 'localhost')"
        );

        SafetyGuard::assertAccountPrivileges(
            (int) $connection->scalar('SELECT @@partial_revokes'),
            $schemaPrivileges,
            $userPrivileges,
            $otherPrivilegeCount,
        );
    }
}

<?php

namespace Tests\MySql;

final class DatabaseIdentityTest extends MySqlTestCase
{
    public function test_live_connection_identity_and_expected_schema_state(): void
    {
        $expectedState = getenv('MYSQL_TEST_EXPECTED_STATE') ?: '';
        $expectedTables = match ($expectedState) {
            'empty' => [],
            'rolled_back' => ['migrations'],
            'migrated' => [
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
            ],
            default => throw new \RuntimeException('MYSQL_TEST_EXPECTED_STATE must be empty, migrated, or rolled_back.'),
        };

        $this->assertSame($expectedTables, $this->tableInventory());
    }

    public function test_account_grants_are_limited_to_the_isolated_database(): void
    {
        $connection = $this->guardMySqlTestConnection();
        $partialRevokes = (int) $connection->scalar('SELECT @@partial_revokes');
        $scopeHex = $partialRevokes === 1
            ? '747261636B70726F5F74657374'
            : '747261636B70726F5C5F74657374';
        $grantee = "CONCAT(QUOTE('trackpro_test_user'), '@', QUOTE('localhost'))";

        $schemaPrivileges = array_map(
            static fn (object $row): array => [$row->scope_hex, $row->privilege, $row->is_grantable],
            $connection->select(
                'SELECT HEX(TABLE_SCHEMA) AS scope_hex, PRIVILEGE_TYPE AS privilege, IS_GRANTABLE AS is_grantable '
                .'FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE = '.$grantee.' ORDER BY PRIVILEGE_TYPE'
            )
        );
        $userPrivileges = array_map(
            static fn (object $row): array => [$row->privilege, $row->is_grantable],
            $connection->select(
                'SELECT PRIVILEGE_TYPE AS privilege, IS_GRANTABLE AS is_grantable '
                .'FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = '.$grantee.' ORDER BY PRIVILEGE_TYPE'
            )
        );
        $otherPrivilegeCount = (int) $connection->scalar(
            'SELECT '
            .'(SELECT COUNT(*) FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE = '.$grantee.') + '
            .'(SELECT COUNT(*) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE = '.$grantee.') + '
            ."(SELECT COUNT(*) FROM information_schema.APPLICABLE_ROLES WHERE GRANTEE = 'trackpro_test_user' "
            ."AND GRANTEE_HOST = 'localhost')"
        );

        $this->assertContains($partialRevokes, [0, 1]);
        $this->assertSame(array_map(
            static fn (string $privilege): array => [$scopeHex, $privilege, 'NO'],
            ['ALTER', 'CREATE', 'DELETE', 'DROP', 'INDEX', 'INSERT', 'REFERENCES', 'SELECT', 'UPDATE']
        ), $schemaPrivileges);
        $this->assertSame([['USAGE', 'NO']], $userPrivileges);
        $this->assertSame(0, $otherPrivilegeCount);
    }
}

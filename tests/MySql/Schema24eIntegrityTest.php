<?php

namespace Tests\MySql;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

final class Schema24eIntegrityTest extends MySql24eTestCase
{
    /** @var list<string> */
    private const EXPECTED_TABLES = [
        'audit_logs',
        'cash_register_sessions',
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
    private const EXPECTED_MIGRATIONS = [
        '0001_01_01_000000_create_users_table',
        '2026_09_05_000001_create_categories_table',
        '2026_09_05_000002_create_products_table',
        '2026_09_05_000003_create_product_variants_table',
        '2026_09_05_000004_create_sales_table',
        '2026_09_05_000005_create_sale_items_table',
        '2026_09_05_000006_create_restocks_table',
        '2026_09_05_000007_create_restock_items_table',
        '2026_09_05_000008_create_stock_movements_table',
        '2026_09_05_000009_create_audit_logs_table',
        '2026_09_17_000010_create_cash_register_sessions_table',
        '2026_09_17_000011_add_cash_register_session_id_to_sales_table',
    ];

    /** @var list<string> */
    private const REGISTER_CHECKS = [
        'cash_register_sessions_close_time_ordered',
        'cash_register_sessions_opening_cash_nonnegative',
        'cash_register_sessions_state_consistent',
    ];

    public function test_production_migrations_and_cash_register_schema_integrity(): void
    {
        $connection = $this->guardedMySql24eConnection();
        $this->assertSafePreMigrationState($connection);

        $connection = $this->guardedMySql24eConnection();

        $exitCode = Artisan::call('migrate', [
            '--database' => self::CONNECTION,
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $this->assertSame(0, $exitCode);

        $connection = $this->guardedMySql24eConnection();
        $this->assertMigratedInventory($connection);
        $this->assertCashRegisterColumns($connection);
        $this->assertCashRegisterIndexes($connection);
        $this->assertCashRegisterForeignKeys($connection);
        $this->assertCashRegisterChecks($connection);
        $this->assertSalesRegisterLink($connection);

        $this->proveDirectConstraintMatrix();
    }

    private function assertSafePreMigrationState(Connection $connection): void
    {
        $tables = $this->tableInventory($connection);

        if ($tables === []) {
            $this->addToAssertionCount(1);

            return;
        }

        if ($tables !== self::EXPECTED_TABLES) {
            throw new MySql24eGuardException('The dedicated #24E database contains an unexpected or partial table inventory.');
        }

        $migrations = $connection->table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(static fn (mixed $migration): string => (string) $migration)
            ->all();

        if ($migrations !== self::EXPECTED_MIGRATIONS) {
            throw new MySql24eGuardException('The dedicated #24E database migration history is incomplete or unexpected.');
        }

        $this->addToAssertionCount(2);
    }

    private function assertMigratedInventory(Connection $connection): void
    {
        $this->assertSame(self::EXPECTED_TABLES, $this->tableInventory($connection));
        $this->assertSame(
            self::EXPECTED_MIGRATIONS,
            $connection->table('migrations')
                ->orderBy('migration')
                ->pluck('migration')
                ->map(static fn (mixed $migration): string => (string) $migration)
                ->all(),
        );
    }

    private function assertCashRegisterColumns(Connection $connection): void
    {
        $columns = $this->columns($connection, 'cash_register_sessions');

        $this->assertSame([
            'active_slot',
            'closed_at',
            'closed_by',
            'created_at',
            'id',
            'opened_at',
            'opened_by',
            'opening_cash',
            'updated_at',
        ], array_keys($columns));

        $this->assertSame('bigint', $columns['id']->data_type);
        $this->assertStringContainsString('unsigned', $columns['id']->column_type);
        $this->assertSame('NO', $columns['id']->is_nullable);

        $this->assertSame('decimal', $columns['opening_cash']->data_type);
        $this->assertSame(16, (int) $columns['opening_cash']->numeric_precision);
        $this->assertSame(2, (int) $columns['opening_cash']->numeric_scale);
        $this->assertSame('NO', $columns['opening_cash']->is_nullable);

        $this->assertSame('bigint', $columns['opened_by']->data_type);
        $this->assertStringContainsString('unsigned', $columns['opened_by']->column_type);
        $this->assertSame('NO', $columns['opened_by']->is_nullable);
        $this->assertSame('timestamp', $columns['opened_at']->data_type);
        $this->assertSame('NO', $columns['opened_at']->is_nullable);

        $this->assertSame('bigint', $columns['closed_by']->data_type);
        $this->assertStringContainsString('unsigned', $columns['closed_by']->column_type);
        $this->assertSame('YES', $columns['closed_by']->is_nullable);
        $this->assertSame('timestamp', $columns['closed_at']->data_type);
        $this->assertSame('YES', $columns['closed_at']->is_nullable);

        $this->assertSame('tinyint', $columns['active_slot']->data_type);
        $this->assertStringContainsString('unsigned', $columns['active_slot']->column_type);
        $this->assertSame('YES', $columns['active_slot']->is_nullable);

        $this->assertSame('timestamp', $columns['created_at']->data_type);
        $this->assertSame('timestamp', $columns['updated_at']->data_type);
    }

    private function assertCashRegisterIndexes(Connection $connection): void
    {
        $indexes = $this->indexes($connection, 'cash_register_sessions');

        $this->assertSame(['id'], $indexes['PRIMARY']['columns']);
        $this->assertFalse($indexes['PRIMARY']['non_unique']);
        $this->assertSame(
            ['active_slot'],
            $indexes['cash_register_sessions_active_slot_unique']['columns'],
        );
        $this->assertFalse($indexes['cash_register_sessions_active_slot_unique']['non_unique']);
        $this->assertSame(
            ['opened_by', 'opened_at'],
            $indexes['cash_register_sessions_opened_by_opened_at_index']['columns'],
        );
        $this->assertSame(
            ['closed_by', 'closed_at'],
            $indexes['cash_register_sessions_closed_by_closed_at_index']['columns'],
        );
        $this->assertSame(
            ['closed_at'],
            $indexes['cash_register_sessions_closed_at_index']['columns'],
        );
    }

    private function assertCashRegisterForeignKeys(Connection $connection): void
    {
        $this->assertForeignKey(
            $connection,
            'cash_register_sessions_opened_by_foreign',
            'cash_register_sessions',
            'opened_by',
            'users',
            'id',
        );
        $this->assertForeignKey(
            $connection,
            'cash_register_sessions_closed_by_foreign',
            'cash_register_sessions',
            'closed_by',
            'users',
            'id',
        );
    }

    private function assertCashRegisterChecks(Connection $connection): void
    {
        $checks = $connection->select(
            'SELECT tc.CONSTRAINT_NAME AS constraint_name, tc.ENFORCED AS enforced, '
            .'cc.CHECK_CLAUSE AS check_clause '
            .'FROM information_schema.TABLE_CONSTRAINTS tc '
            .'INNER JOIN information_schema.CHECK_CONSTRAINTS cc '
            .'ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA '
            .'AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME '
            .'WHERE tc.CONSTRAINT_SCHEMA = ? AND tc.TABLE_NAME = ? '
            .'AND tc.CONSTRAINT_TYPE = ? ORDER BY tc.CONSTRAINT_NAME',
            [self::DATABASE, 'cash_register_sessions', 'CHECK'],
        );

        $this->assertSame(
            self::REGISTER_CHECKS,
            array_map(static fn (object $row): string => (string) $row->constraint_name, $checks),
        );
        foreach ($checks as $check) {
            $this->assertSame('YES', $check->enforced, $check->constraint_name);
            $this->assertNotSame('', trim((string) $check->check_clause), $check->constraint_name);
        }
    }

    private function assertSalesRegisterLink(Connection $connection): void
    {
        $salesColumns = $this->columns($connection, 'sales');
        $sessionColumns = $this->columns($connection, 'cash_register_sessions');
        $column = $salesColumns['cash_register_session_id'];

        $this->assertSame('YES', $column->is_nullable);
        $this->assertSame($sessionColumns['id']->data_type, $column->data_type);
        $this->assertSame($sessionColumns['id']->column_type, $column->column_type);

        $this->assertForeignKey(
            $connection,
            'sales_cash_register_session_id_foreign',
            'sales',
            'cash_register_session_id',
            'cash_register_sessions',
            'id',
        );

        $indexes = $this->indexes($connection, 'sales');
        $this->assertSame(
            ['cash_register_session_id', 'created_at'],
            $indexes['sales_cash_register_session_id_created_at_index']['columns'],
        );
    }

    private function proveDirectConstraintMatrix(): void
    {
        $connection = $this->guardedMySql24eConnection();
        $userIds = [];
        $sessionIds = [];
        $saleIds = [];

        try {
            $marker = '24e_schema_'.Str::lower(Str::random(12));
            $userIds[] = $userId = (int) $connection->table('users')->insertGetId([
                'name' => '24E Schema Fixture',
                'username' => $marker,
                'password' => password_hash('24e-schema-test-only', PASSWORD_BCRYPT),
                'role' => 'staff',
                'status' => 'active',
            ]);

            $openedAt = '2026-09-18 09:00:00';
            $closedAt = '2026-09-18 10:00:00';

            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '-0.01',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => 1,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => null,
                'closed_by' => null,
                'closed_at' => null,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => 0,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => 2,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => 1,
                'closed_by' => $userId,
                'closed_at' => null,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => 1,
                'closed_by' => null,
                'closed_at' => $closedAt,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => null,
                'closed_by' => null,
                'closed_at' => $closedAt,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => null,
                'closed_by' => $userId,
                'closed_at' => null,
            ]);
            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => null,
                'closed_by' => $userId,
                'closed_at' => '2026-09-18 08:59:59',
            ]);

            $sessionIds[] = $activeSessionId = $this->insertRegister($connection, [
                'opening_cash' => '0.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => 1,
            ]);
            $this->assertSame(
                '0.00',
                $connection->table('cash_register_sessions')
                    ->where('id', $activeSessionId)
                    ->value('opening_cash'),
            );

            $sessionIds[] = $maximumSessionId = $this->insertRegister($connection, [
                'opening_cash' => '99999999999999.99',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => null,
                'closed_by' => $userId,
                'closed_at' => $closedAt,
            ]);
            $this->assertSame(
                '99999999999999.99',
                $connection->table('cash_register_sessions')
                    ->where('id', $maximumSessionId)
                    ->value('opening_cash'),
            );

            $sessionIds[] = $this->insertRegister($connection, [
                'opening_cash' => '50.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => null,
                'closed_by' => $userId,
                'closed_at' => $closedAt,
            ]);
            $this->assertSame(
                2,
                $connection->table('cash_register_sessions')->whereNull('active_slot')->count(),
            );
            $this->assertSame(
                1,
                $connection->table('cash_register_sessions')->where('active_slot', 1)->count(),
            );

            $saleIds[] = $legacySaleId = $this->insertSale($connection, $userId, null);
            $this->assertNull(
                $connection->table('sales')
                    ->where('id', $legacySaleId)
                    ->value('cash_register_session_id'),
            );

            $saleIds[] = $linkedSaleId = $this->insertSale($connection, $userId, $maximumSessionId);
            $this->assertSame(
                $maximumSessionId,
                (int) $connection->table('sales')
                    ->where('id', $linkedSaleId)
                    ->value('cash_register_session_id'),
            );

            $this->assertRegisterInsertRejected($connection, $sessionIds, [
                'opening_cash' => '25.00',
                'opened_by' => $userId,
                'opened_at' => $openedAt,
                'active_slot' => 1,
            ]);

            $this->assertOperationRejected(
                $connection,
                'cash_register_sessions',
                fn (): int => $connection->table('cash_register_sessions')
                    ->where('id', $maximumSessionId)
                    ->delete(),
            );
        } finally {
            $cleanup = $this->guardedMySql24eConnection();
            $this->deleteExactRows($cleanup, 'sales', $saleIds);
            $this->deleteExactRows($cleanup, 'cash_register_sessions', $sessionIds);
            $this->deleteExactRows($cleanup, 'users', $userIds);

            $this->assertNoExactRows($cleanup, 'sales', $saleIds);
            $this->assertNoExactRows($cleanup, 'cash_register_sessions', $sessionIds);
            $this->assertNoExactRows($cleanup, 'users', $userIds);
        }

        $this->assertSame(self::DATABASE, $this->guardedMySql24eConnection()->getDatabaseName());
    }

    /** @param array<string, mixed> $attributes */
    private function assertRegisterInsertRejected(
        Connection $connection,
        array &$sessionIds,
        array $attributes,
    ): void {
        $before = $connection->table('cash_register_sessions')->count();

        try {
            $sessionIds[] = $this->insertRegister($connection, $attributes);
            $this->fail('MySQL unexpectedly accepted an invalid cash register session.');
        } catch (QueryException $exception) {
            $this->assertConstraintException($exception);
        }

        $this->assertSame($before, $connection->table('cash_register_sessions')->count());
    }

    private function assertOperationRejected(
        Connection $connection,
        string $table,
        callable $operation,
    ): void {
        $before = $connection->table($table)->count();

        try {
            $operation();
            $this->fail('MySQL unexpectedly accepted a prohibited database operation.');
        } catch (QueryException $exception) {
            $this->assertConstraintException($exception);
        }

        $this->assertSame($before, $connection->table($table)->count());
    }

    private function assertConstraintException(QueryException $exception): void
    {
        $this->assertContains((string) $exception->getCode(), ['23000', 'HY000']);
        $this->assertNotEmpty($exception->errorInfo);
    }

    /** @param array<string, mixed> $attributes */
    private function insertRegister(Connection $connection, array $attributes): int
    {
        return (int) $connection->table('cash_register_sessions')->insertGetId($attributes);
    }

    private function insertSale(Connection $connection, int $userId, ?int $sessionId): int
    {
        return (int) $connection->table('sales')->insertGetId([
            'checkout_token' => (string) Str::uuid(),
            'recorded_by' => $userId,
            'cash_register_session_id' => $sessionId,
            'status' => 'completed',
            'total_amount' => '100.00',
            'cash_received' => '100.00',
            'change_amount' => '0.00',
            'void_reason' => null,
            'voided_by' => null,
            'voided_at' => null,
        ]);
    }

    /** @param list<int> $ids */
    private function deleteExactRows(Connection $connection, string $table, array $ids): void
    {
        if ($ids !== []) {
            $connection->table($table)->whereIn('id', $ids)->delete();
        }
    }

    /** @param list<int> $ids */
    private function assertNoExactRows(Connection $connection, string $table, array $ids): void
    {
        if ($ids !== []) {
            $this->assertSame(0, $connection->table($table)->whereIn('id', $ids)->count());
        }
    }

    /** @return list<string> */
    private function tableInventory(Connection $connection): array
    {
        return array_map(
            static fn (object $row): string => (string) $row->table_name,
            $connection->select(
                'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES '
                .'WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
                [self::DATABASE],
            ),
        );
    }

    /** @return array<string, object> */
    private function columns(Connection $connection, string $table): array
    {
        $columns = [];
        foreach ($connection->select(
            'SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, '
            .'IS_NULLABLE AS is_nullable, NUMERIC_PRECISION AS numeric_precision, '
            .'NUMERIC_SCALE AS numeric_scale FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY COLUMN_NAME',
            [self::DATABASE, $table],
        ) as $column) {
            $columns[(string) $column->column_name] = $column;
        }

        return $columns;
    }

    /** @return array<string, array{non_unique: bool, columns: list<string>}> */
    private function indexes(Connection $connection, string $table): array
    {
        $indexes = [];
        foreach ($connection->select(
            'SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, '
            .'SEQ_IN_INDEX AS sequence_number, COLUMN_NAME AS column_name '
            .'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            .'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [self::DATABASE, $table],
        ) as $index) {
            $name = (string) $index->index_name;
            $indexes[$name] ??= [
                'non_unique' => (bool) $index->non_unique,
                'columns' => [],
            ];
            $indexes[$name]['columns'][] = (string) $index->column_name;
        }

        return $indexes;
    }

    private function assertForeignKey(
        Connection $connection,
        string $constraint,
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
    ): void {
        $metadata = $connection->selectOne(
            'SELECT kcu.TABLE_NAME AS table_name, kcu.COLUMN_NAME AS column_name, '
            .'kcu.REFERENCED_TABLE_NAME AS referenced_table_name, '
            .'kcu.REFERENCED_COLUMN_NAME AS referenced_column_name, '
            .'rc.DELETE_RULE AS delete_rule, rc.UPDATE_RULE AS update_rule '
            .'FROM information_schema.KEY_COLUMN_USAGE kcu '
            .'INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc '
            .'ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA '
            .'AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME '
            .'WHERE kcu.CONSTRAINT_SCHEMA = ? AND kcu.CONSTRAINT_NAME = ?',
            [self::DATABASE, $constraint],
        );

        $this->assertNotNull($metadata);
        $this->assertSame($table, $metadata->table_name);
        $this->assertSame($column, $metadata->column_name);
        $this->assertSame($referencedTable, $metadata->referenced_table_name);
        $this->assertSame($referencedColumn, $metadata->referenced_column_name);
        $this->assertSame('RESTRICT', $metadata->delete_rule);
        $this->assertSame('RESTRICT', $metadata->update_rule);
    }
}

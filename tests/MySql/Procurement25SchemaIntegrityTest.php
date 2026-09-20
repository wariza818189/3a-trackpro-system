<?php

namespace Tests\MySql;

use AssertionError;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use PDOException;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Throwable;

final class Procurement25SchemaIntegrityTest extends MySql24eTestCase
{
    /** @var list<string> */
    private const ALLOWED_REJECTION_SQL_STATES = ['01000', '23000', 'HY000'];

    /** @var list<string> */
    private const PRE_25_TABLES = [
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
    private const EXPECTED_TABLES = [
        'audit_logs',
        'cash_register_sessions',
        'categories',
        'migrations',
        'product_variants',
        'products',
        'purchase_order_items',
        'purchase_orders',
        'restock_items',
        'restocks',
        'sale_items',
        'sales',
        'stock_movements',
        'users',
    ];

    /** @var list<string> */
    private const PRE_25_MIGRATIONS = [
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
        '2026_09_20_000012_create_purchase_orders_table',
        '2026_09_20_000013_create_purchase_order_items_table',
        '2026_09_20_000014_add_parent_purchase_order_id_to_purchase_orders_table',
        '2026_09_20_000015_add_variant_movement_type_index_to_stock_movements_table',
    ];

    public function test_purchase_order_schema_and_direct_constraints(): void
    {
        $connection = $this->guardedMySql24eConnection();
        $this->prepareExactSchema($connection);

        $connection = $this->guardedMySql24eConnection();
        $this->assertExactInventory($connection);
        $this->assertPurchaseOrderColumns($connection);
        $this->assertPurchaseOrderItemColumns($connection);
        $this->assertIndexes($connection);
        $this->assertForeignKeys($connection);
        $this->assertChecks($connection);
        $this->proveDirectConstraintMatrix($connection);
    }

    private function prepareExactSchema(Connection $connection): void
    {
        $tables = $this->tableInventory($connection);
        if ($tables === []) {
            $this->runMigrations();

            return;
        }

        if ($tables === self::PRE_25_TABLES) {
            if ($this->migrationInventory($connection) !== self::PRE_25_MIGRATIONS) {
                throw new MySql24eGuardException('The dedicated #24E pre-#25 migration history is not exact.');
            }

            $this->runMigrations();

            return;
        }

        if ($tables === self::EXPECTED_TABLES) {
            if ($this->migrationInventory($connection) !== self::EXPECTED_MIGRATIONS) {
                throw new MySql24eGuardException('The dedicated #24E #25A migration history is not exact.');
            }

            $this->addToAssertionCount(1);

            return;
        }

        throw new MySql24eGuardException('The dedicated #24E database contains an unexpected or partial table inventory.');
    }

    private function runMigrations(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => self::CONNECTION,
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $this->assertSame(0, $exitCode);
    }

    private function assertExactInventory(Connection $connection): void
    {
        $this->assertSame(self::EXPECTED_TABLES, $this->tableInventory($connection));
        $this->assertSame(self::EXPECTED_MIGRATIONS, $this->migrationInventory($connection));
    }

    private function assertPurchaseOrderColumns(Connection $connection): void
    {
        $columns = $this->columns($connection, 'purchase_orders');

        $this->assertSame([
            'created_at',
            'created_by',
            'id',
            'notes',
            'parent_purchase_order_id',
            'status',
            'submission_token',
            'supplier_name',
            'updated_at',
        ], array_keys($columns));
        $this->assertUnsignedBigint($columns['id'], false);
        $this->assertUnsignedBigint($columns['created_by'], false);
        $this->assertUnsignedBigint($columns['parent_purchase_order_id'], true);
        $this->assertSame('char', $columns['submission_token']->data_type);
        $this->assertSame(36, (int) $columns['submission_token']->character_maximum_length);
        $this->assertSame('NO', $columns['submission_token']->is_nullable);
        $this->assertNull($columns['submission_token']->column_default);
        $this->assertSame('varchar', $columns['supplier_name']->data_type);
        $this->assertSame(150, (int) $columns['supplier_name']->character_maximum_length);
        $this->assertSame('NO', $columns['supplier_name']->is_nullable);
        $this->assertNull($columns['supplier_name']->column_default);
        $this->assertSame('enum', $columns['status']->data_type);
        $this->assertSame(
            "enum('pending','partially_received','completed','closed_with_remainder')",
            $columns['status']->column_type,
        );
        $this->assertSame('pending', $columns['status']->column_default);
        $this->assertSame('NO', $columns['status']->is_nullable);
        $this->assertSame('text', $columns['notes']->data_type);
        $this->assertSame('YES', $columns['notes']->is_nullable);
        $this->assertNull($columns['notes']->column_default);
        $this->assertSame('timestamp', $columns['created_at']->data_type);
        $this->assertSame('YES', $columns['created_at']->is_nullable);
        $this->assertNull($columns['created_at']->column_default);
        $this->assertSame('timestamp', $columns['updated_at']->data_type);
        $this->assertSame('YES', $columns['updated_at']->is_nullable);
        $this->assertNull($columns['updated_at']->column_default);
    }

    private function assertPurchaseOrderItemColumns(Connection $connection): void
    {
        $columns = $this->columns($connection, 'purchase_order_items');

        $this->assertSame([
            'created_at',
            'expected_unit_cost',
            'id',
            'ordered_quantity',
            'product_name_snapshot',
            'product_variant_id',
            'purchase_order_id',
            'size_snapshot',
            'thickness_snapshot',
            'type_series_snapshot',
            'unit_snapshot',
            'updated_at',
        ], array_keys($columns));
        $this->assertUnsignedBigint($columns['id'], false);
        $this->assertUnsignedBigint($columns['purchase_order_id'], false);
        $this->assertUnsignedBigint($columns['product_variant_id'], false);
        $this->assertStringColumn($columns['product_name_snapshot'], 150, null);
        $this->assertStringColumn($columns['size_snapshot'], 80, '');
        $this->assertStringColumn($columns['type_series_snapshot'], 80, '');
        $this->assertStringColumn($columns['thickness_snapshot'], 40, '');
        $this->assertStringColumn($columns['unit_snapshot'], 30, null);
        $this->assertDecimalColumn($columns['ordered_quantity'], 14, 3);
        $this->assertDecimalColumn($columns['expected_unit_cost'], 12, 2);
        $this->assertSame('timestamp', $columns['created_at']->data_type);
        $this->assertSame('YES', $columns['created_at']->is_nullable);
        $this->assertNull($columns['created_at']->column_default);
        $this->assertSame('timestamp', $columns['updated_at']->data_type);
        $this->assertSame('YES', $columns['updated_at']->is_nullable);
        $this->assertNull($columns['updated_at']->column_default);
    }

    private function assertIndexes(Connection $connection): void
    {
        $purchaseOrderIndexes = $this->indexes($connection, 'purchase_orders');
        $this->assertSame(['submission_token'], $purchaseOrderIndexes['purchase_orders_submission_token_unique']['columns']);
        $this->assertFalse($purchaseOrderIndexes['purchase_orders_submission_token_unique']['non_unique']);
        $this->assertSame(['status', 'created_at'], $purchaseOrderIndexes['purchase_orders_status_created_at_index']['columns']);
        $this->assertTrue($purchaseOrderIndexes['purchase_orders_status_created_at_index']['non_unique']);
        $this->assertSame(['created_by', 'created_at'], $purchaseOrderIndexes['purchase_orders_created_by_created_at_index']['columns']);
        $this->assertTrue($purchaseOrderIndexes['purchase_orders_created_by_created_at_index']['non_unique']);
        $this->assertSame(['parent_purchase_order_id'], $purchaseOrderIndexes['purchase_orders_parent_purchase_order_id_index']['columns']);
        $this->assertTrue($purchaseOrderIndexes['purchase_orders_parent_purchase_order_id_index']['non_unique']);

        $itemIndexes = $this->indexes($connection, 'purchase_order_items');
        $this->assertSame(['purchase_order_id', 'product_variant_id'], $itemIndexes['po_items_po_variant_unique']['columns']);
        $this->assertFalse($itemIndexes['po_items_po_variant_unique']['non_unique']);
        $this->assertSame(['product_variant_id', 'purchase_order_id'], $itemIndexes['po_items_variant_po_index']['columns']);
        $this->assertTrue($itemIndexes['po_items_variant_po_index']['non_unique']);

        $movementIndexes = $this->indexes($connection, 'stock_movements');
        $this->assertSame(['product_variant_id', 'movement_type'], $movementIndexes['movements_variant_type_index']['columns']);
        $this->assertTrue($movementIndexes['movements_variant_type_index']['non_unique']);
    }

    private function assertForeignKeys(Connection $connection): void
    {
        $this->assertForeignKey($connection, 'purchase_orders_created_by_foreign', 'purchase_orders', 'created_by', 'users');
        $this->assertForeignKey($connection, 'purchase_orders_parent_purchase_order_id_foreign', 'purchase_orders', 'parent_purchase_order_id', 'purchase_orders');
        $this->assertForeignKey($connection, 'purchase_order_items_purchase_order_id_foreign', 'purchase_order_items', 'purchase_order_id', 'purchase_orders');
        $this->assertForeignKey($connection, 'purchase_order_items_product_variant_id_foreign', 'purchase_order_items', 'product_variant_id', 'product_variants');
    }

    private function assertChecks(Connection $connection): void
    {
        $this->assertSame(
            ['purchase_orders_supplier_name_nonblank'],
            array_keys($this->checks($connection, 'purchase_orders')),
        );
        $this->assertSame(
            ['purchase_order_items_cost_nonnegative', 'purchase_order_items_quantity_positive'],
            array_keys($this->checks($connection, 'purchase_order_items')),
        );

        foreach (['purchase_orders', 'purchase_order_items'] as $table) {
            foreach ($this->checks($connection, $table) as $name => $check) {
                $this->assertSame('YES', $check->enforced, $name);
                $this->assertNotSame('', trim((string) $check->check_clause), $name);
            }
        }
    }

    private function proveDirectConstraintMatrix(Connection $connection): void
    {
        $tables = ['purchase_order_items', 'purchase_orders', 'product_variants', 'products', 'categories', 'users'];
        $baseline = [];
        foreach ($tables as $table) {
            $baseline[$table] = $connection->table($table)->count();
        }

        $this->runDiagnosticPhase(
            'TRANSACTION_BEGIN',
            function () use ($connection): void {
                $connection->beginTransaction();
            },
        );

        try {
            $marker = Str::lower(Str::random(12));
            $userId = (int) $connection->table('users')->insertGetId([
                'name' => 'Procurement Schema Fixture',
                'username' => 'po_'.$marker,
                'password' => password_hash('procurement-schema-test-only', PASSWORD_BCRYPT),
                'role' => 'admin',
                'status' => 'active',
            ]);
            $categoryId = (int) $connection->table('categories')->insertGetId([
                'name' => 'PO Category '.$marker,
                'status' => 'active',
            ]);
            $productId = (int) $connection->table('products')->insertGetId([
                'category_id' => $categoryId,
                'name' => 'PO Product '.$marker,
                'status' => 'active',
            ]);
            $variantIds = [
                $this->insertVariant($connection, $productId, 'A'),
                $this->insertVariant($connection, $productId, 'B'),
            ];
            [$missingUserId, $missingVariantId] = $this->runDiagnosticPhase(
                'MISSING_REFERENCE_IDS',
                fn (): array => [
                    (int) $connection->table('users')->max('id') + 1,
                    (int) $connection->table('product_variants')->max('id') + 1,
                ],
            );

            $token = (string) Str::uuid();
            $parentId = $this->insertPurchaseOrder($connection, $userId, $token, '  Acme Supply  ', null);
            $childId = $this->insertPurchaseOrder($connection, $userId, (string) Str::uuid(), 'Follow-up Supply', $parentId);
            $this->assertSame('pending', $connection->table('purchase_orders')->where('id', $parentId)->value('status'));
            $this->assertSame('  Acme Supply  ', $connection->table('purchase_orders')->where('id', $parentId)->value('supplier_name'));
            $this->assertNull($connection->table('purchase_orders')->where('id', $parentId)->value('parent_purchase_order_id'));
            $this->assertSame($parentId, (int) $connection->table('purchase_orders')->where('id', $childId)->value('parent_purchase_order_id'));

            $itemId = $this->insertItem($connection, $parentId, $variantIds[0], '1.250', '0.00');
            $this->assertSame('1.250', $connection->table('purchase_order_items')->where('id', $itemId)->value('ordered_quantity'));
            $this->assertSame('0.00', $connection->table('purchase_order_items')->where('id', $itemId)->value('expected_unit_cost'));

            $this->assertInsertRejected(
                'PO_SUPPLIER_BLANK',
                'CHECK',
                fn (): int => $this->insertPurchaseOrder($connection, $userId, (string) Str::uuid(), '   ', null),
            );
            $this->assertInsertRejected(
                'PO_TOKEN_DUPLICATE',
                'UNIQUE',
                fn (): int => $this->insertPurchaseOrder($connection, $userId, $token, 'Duplicate Token', null),
            );
            $this->assertInsertRejected(
                'PO_CREATOR_FK',
                'FOREIGN_KEY',
                fn (): int => $this->insertPurchaseOrder($connection, $missingUserId, (string) Str::uuid(), 'Missing Creator', null),
            );
            $this->assertInsertRejected(
                'PO_STATUS_ENUM',
                'STRICT_ENUM',
                fn (): int => (int) $connection->table('purchase_orders')->insertGetId([
                    'submission_token' => (string) Str::uuid(),
                    'created_by' => $userId,
                    'supplier_name' => 'Invalid Status',
                    'status' => 'cancelled',
                ]),
            );

            $this->assertInsertRejected(
                'ITEM_QUANTITY_ZERO',
                'CHECK',
                fn (): int => $this->insertItem($connection, $parentId, $variantIds[1], '0.000', '1.00'),
            );
            $this->assertInsertRejected(
                'ITEM_QUANTITY_NEGATIVE',
                'CHECK',
                fn (): int => $this->insertItem($connection, $parentId, $variantIds[1], '-0.001', '1.00'),
            );
            $this->assertInsertRejected(
                'ITEM_COST_NEGATIVE',
                'CHECK',
                fn (): int => $this->insertItem($connection, $parentId, $variantIds[1], '1.000', '-0.01'),
            );
            $this->assertInsertRejected(
                'ITEM_PO_VARIANT_DUPLICATE',
                'UNIQUE',
                fn (): int => $this->insertItem($connection, $parentId, $variantIds[0], '2.000', '1.00'),
            );
            $missingPurchaseOrderId = $this->runDiagnosticPhase(
                'ITEM_PO_FK_REFERENCE_ID',
                fn (): int => (int) $connection->table('purchase_orders')->max('id') + 1,
            );
            $this->assertInsertRejected(
                'ITEM_PO_FK',
                'FOREIGN_KEY',
                fn (): int => $this->insertItem($connection, $missingPurchaseOrderId, $variantIds[1], '1.000', '1.00'),
            );
            $this->assertInsertRejected(
                'ITEM_VARIANT_FK',
                'FOREIGN_KEY',
                fn (): int => $this->insertItem($connection, $parentId, $missingVariantId, '1.000', '1.00'),
            );
            $this->assertUpdateRejected(
                'PO_PARENT_FK',
                'FOREIGN_KEY',
                fn (): int => $connection->table('purchase_orders')
                    ->where('id', $childId)
                    ->update(['parent_purchase_order_id' => $missingPurchaseOrderId]),
            );
        } finally {
            $transactionIsActive = $this->runDiagnosticPhase(
                'ROLLBACK_STATE_CHECK',
                fn (): bool => $connection->getPdo()->inTransaction(),
            );

            if ($transactionIsActive) {
                $this->runDiagnosticPhase(
                    'ROLLBACK_EXECUTION',
                    function () use ($connection): void {
                        $connection->rollBack();
                    },
                );
            }
        }

        $this->runDiagnosticPhase(
            'POST_ROLLBACK_BASELINE',
            function () use ($baseline, $connection): void {
                foreach ($baseline as $table => $count) {
                    $this->assertSame(
                        $count,
                        $connection->table($table)->count(),
                        'Procurement25 diagnostic [POST_ROLLBACK_BASELINE]: '.$table,
                    );
                }
            },
        );
        $this->runDiagnosticPhase(
            'FINAL_GUARDED_CONNECTION',
            function (): void {
                $this->assertSame(
                    self::DATABASE,
                    $this->guardedMySql24eConnection()->getDatabaseName(),
                    'Procurement25 diagnostic [FINAL_GUARDED_CONNECTION]',
                );
            },
        );
    }

    private function insertVariant(Connection $connection, int $productId, string $size): int
    {
        return (int) $connection->table('product_variants')->insertGetId([
            'product_id' => $productId,
            'size' => $size,
            'type_series' => '',
            'thickness' => '',
            'unit' => 'piece',
            'quantity_mode' => 'whole',
            'cost_price' => null,
            'selling_price' => '10.00',
            'current_stock' => '0.000',
            'low_stock_threshold' => '1.000',
            'status' => 'active',
        ]);
    }

    private function insertPurchaseOrder(
        Connection $connection,
        int $userId,
        string $token,
        string $supplier,
        ?int $parentId,
    ): int {
        return (int) $connection->table('purchase_orders')->insertGetId([
            'parent_purchase_order_id' => $parentId,
            'submission_token' => $token,
            'created_by' => $userId,
            'supplier_name' => $supplier,
            'notes' => null,
        ]);
    }

    private function insertItem(
        Connection $connection,
        int $purchaseOrderId,
        int $variantId,
        string $quantity,
        string $cost,
    ): int {
        return (int) $connection->table('purchase_order_items')->insertGetId([
            'purchase_order_id' => $purchaseOrderId,
            'product_variant_id' => $variantId,
            'product_name_snapshot' => 'Schema Fixture',
            'size_snapshot' => '',
            'type_series_snapshot' => '',
            'thickness_snapshot' => '',
            'unit_snapshot' => 'piece',
            'ordered_quantity' => $quantity,
            'expected_unit_cost' => $cost,
        ]);
    }

    private function assertInsertRejected(
        string $caseLabel,
        string $expectedCategory,
        Closure $operation,
    ): void {
        $this->assertConstraintRejected($caseLabel, $expectedCategory, $operation, 'row');
    }

    private function assertUpdateRejected(
        string $caseLabel,
        string $expectedCategory,
        Closure $operation,
    ): void {
        $this->assertConstraintRejected($caseLabel, $expectedCategory, $operation, 'update');
    }

    private function assertConstraintRejected(
        string $caseLabel,
        string $expectedCategory,
        Closure $operation,
        string $operationType,
    ): void {
        try {
            $operation();
            $this->fail(
                "Procurement25 diagnostic [{$caseLabel}]: expected {$expectedCategory} {$operationType} rejection.",
            );
        } catch (QueryException $exception) {
            $this->assertRejectedQueryException($caseLabel, $expectedCategory, $exception);
        } catch (AssertionFailedError|AssertionError $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->sanitizedDiagnosticException($caseLabel, $exception);
        }
    }

    private function assertRejectedQueryException(
        string $caseLabel,
        string $expectedCategory,
        QueryException $exception,
    ): void {
        try {
            $sqlState = $this->safeSqlState($exception) ?? 'unavailable';
            $nativeCode = $this->safeNativeCode($exception);
            $diagnostic = "Procurement25 diagnostic [{$caseLabel}]"
                ." category={$expectedCategory} sqlstate={$sqlState}"
                .($nativeCode === null ? '' : " native={$nativeCode}");

            $this->assertContains($sqlState, self::ALLOWED_REJECTION_SQL_STATES, $diagnostic);
            $this->assertTrue(
                is_array($exception->errorInfo) && $exception->errorInfo !== [],
                $diagnostic.' missing structured driver category.',
            );
        } catch (AssertionFailedError|AssertionError $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->sanitizedDiagnosticException($caseLabel, $exception);
        }
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    private function runDiagnosticPhase(string $phaseLabel, Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (AssertionFailedError|AssertionError $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->sanitizedDiagnosticException($phaseLabel, $exception);
        }
    }

    private function sanitizedDiagnosticException(string $label, Throwable $exception): RuntimeException
    {
        $sqlState = $this->safeSqlState($exception);
        $nativeCode = $this->safeNativeCode($exception);
        $message = "Procurement25 diagnostic [{$label}]: unexpected ".$exception::class
            .($sqlState === null ? '' : " sqlstate={$sqlState}")
            .($nativeCode === null ? '' : " native={$nativeCode}");

        return new RuntimeException($message);
    }

    private function safeSqlState(Throwable $exception): ?string
    {
        $code = $exception->getCode();
        if (! is_int($code) && ! is_string($code)) {
            return null;
        }

        $normalized = strtoupper((string) $code);

        return preg_match('/\A[A-Z0-9]{5}\z/', $normalized) === 1 ? $normalized : null;
    }

    private function safeNativeCode(Throwable $exception): ?int
    {
        if (! $exception instanceof PDOException || ! is_array($exception->errorInfo)) {
            return null;
        }

        $nativeCode = $exception->errorInfo[1] ?? null;
        if (is_int($nativeCode)) {
            return $nativeCode;
        }
        if (is_string($nativeCode) && ctype_digit($nativeCode)) {
            return (int) $nativeCode;
        }

        return null;
    }

    private function assertUnsignedBigint(object $column, bool $nullable): void
    {
        $this->assertSame('bigint', $column->data_type);
        $this->assertStringContainsString('unsigned', $column->column_type);
        $this->assertSame($nullable ? 'YES' : 'NO', $column->is_nullable);
        $this->assertNull($column->column_default);
    }

    private function assertStringColumn(object $column, int $length, ?string $default): void
    {
        $this->assertSame('varchar', $column->data_type);
        $this->assertSame($length, (int) $column->character_maximum_length);
        $this->assertSame('NO', $column->is_nullable);
        $this->assertSame($default, $column->column_default);
    }

    private function assertDecimalColumn(object $column, int $precision, int $scale): void
    {
        $this->assertSame('decimal', $column->data_type);
        $this->assertSame($precision, (int) $column->numeric_precision);
        $this->assertSame($scale, (int) $column->numeric_scale);
        $this->assertSame('NO', $column->is_nullable);
        $this->assertNull($column->column_default);
    }

    /** @return list<string> */
    private function tableInventory(Connection $connection): array
    {
        return array_map(
            static fn (object $row): string => (string) $row->table_name,
            $connection->select(
                'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES '
                .'WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
                [self::DATABASE, 'BASE TABLE'],
            ),
        );
    }

    /** @return list<string> */
    private function migrationInventory(Connection $connection): array
    {
        return $connection->table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(static fn (mixed $migration): string => (string) $migration)
            ->all();
    }

    /** @return array<string, object> */
    private function columns(Connection $connection, string $table): array
    {
        $columns = [];
        foreach ($connection->select(
            'SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, '
            .'IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, '
            .'CHARACTER_MAXIMUM_LENGTH AS character_maximum_length, '
            .'NUMERIC_PRECISION AS numeric_precision, NUMERIC_SCALE AS numeric_scale '
            .'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            .'ORDER BY COLUMN_NAME',
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
            'SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name '
            .'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            .'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [self::DATABASE, $table],
        ) as $index) {
            $name = (string) $index->index_name;
            $indexes[$name] ??= ['non_unique' => (bool) $index->non_unique, 'columns' => []];
            $indexes[$name]['columns'][] = (string) $index->column_name;
        }

        return $indexes;
    }

    /** @return array<string, object> */
    private function checks(Connection $connection, string $table): array
    {
        $checks = [];
        foreach ($connection->select(
            'SELECT tc.CONSTRAINT_NAME AS constraint_name, tc.ENFORCED AS enforced, '
            .'cc.CHECK_CLAUSE AS check_clause FROM information_schema.TABLE_CONSTRAINTS tc '
            .'INNER JOIN information_schema.CHECK_CONSTRAINTS cc '
            .'ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME '
            .'WHERE tc.CONSTRAINT_SCHEMA = ? AND tc.TABLE_NAME = ? AND tc.CONSTRAINT_TYPE = ? '
            .'ORDER BY tc.CONSTRAINT_NAME',
            [self::DATABASE, $table, 'CHECK'],
        ) as $check) {
            $checks[(string) $check->constraint_name] = $check;
        }

        return $checks;
    }

    private function assertForeignKey(
        Connection $connection,
        string $constraint,
        string $table,
        string $column,
        string $referencedTable,
    ): void {
        $metadata = $connection->selectOne(
            'SELECT kcu.TABLE_NAME AS table_name, kcu.COLUMN_NAME AS column_name, '
            .'kcu.REFERENCED_TABLE_NAME AS referenced_table_name, '
            .'kcu.REFERENCED_COLUMN_NAME AS referenced_column_name, '
            .'rc.DELETE_RULE AS delete_rule, rc.UPDATE_RULE AS update_rule '
            .'FROM information_schema.KEY_COLUMN_USAGE kcu '
            .'INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc '
            .'ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME '
            .'WHERE kcu.CONSTRAINT_SCHEMA = ? AND kcu.CONSTRAINT_NAME = ?',
            [self::DATABASE, $constraint],
        );

        $this->assertNotNull($metadata);
        $this->assertSame($table, $metadata->table_name);
        $this->assertSame($column, $metadata->column_name);
        $this->assertSame($referencedTable, $metadata->referenced_table_name);
        $this->assertSame('id', $metadata->referenced_column_name);
        $this->assertSame('RESTRICT', $metadata->delete_rule);
        $this->assertSame('RESTRICT', $metadata->update_rule);
    }
}

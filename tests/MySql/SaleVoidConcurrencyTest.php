<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CashRegister\OpenCashRegister;
use App\Services\Inventory\RecordOpeningInventory;
use App\Services\Sales\RecordSale;
use App\Services\Sales\SaleVoidService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Throwable;

final class SaleVoidConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const ALREADY_VOIDED_MESSAGE = 'Only a completed Sale may be voided once.';

    /** @var list<string> */
    private const TABLES = [
        'audit_logs', 'cash_register_sessions', 'categories', 'migrations', 'product_variants',
        'products', 'purchase_order_item_transfers', 'purchase_order_items', 'purchase_orders',
        'restock_damage_items', 'restock_items', 'restocks', 'sale_items', 'sales', 'stock_movements', 'users',
    ];

    /** @var list<string> */
    private const MIGRATIONS = [
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
        '2026_09_24_000001_add_purchase_order_links_to_restocks',
        '2026_09_24_000002_create_purchase_order_item_transfers_table',
        '2026_09_25_000001_create_restock_damage_items_table',
    ];

    /** @var list<string> */
    private const DOMAIN_TABLES = [
        'users',
        'categories',
        'products',
        'product_variants',
        'cash_register_sessions',
        'sales',
        'sale_items',
        'stock_movements',
        'audit_logs',
    ];

    protected function guardedConcurrencyConnection(): Connection
    {
        // The shared concurrency harness ledger predates the later procurement migrations.
        $db = $this->guardedMySql24eConnection();
        if ($db->getName() !== self::CONNECTION
            || $db->scalar('SELECT DATABASE()') !== self::DATABASE
            || (int) $db->scalar('SELECT @@autocommit') !== 1
            || strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))) !== 'REPEATABLE-READ') {
            throw new MySql24eGuardException('The Sale Void MySQL session is not exact.');
        }

        $tables = array_map(static fn (object $row): string => (string) $row->name, $db->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [self::DATABASE, 'BASE TABLE'],
        ));
        $migrations = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($tables !== self::TABLES || $migrations !== self::MIGRATIONS) {
            throw new MySql24eGuardException('The Sale Void MySQL table inventory or migration ledger is not exact.');
        }

        $engines = $db->select(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
            [self::DATABASE, 'BASE TABLE'],
        );
        if (count($engines) !== count(self::TABLES)
            || array_filter($engines, static fn (object $row): bool => $row->engine !== 'InnoDB') !== []) {
            throw new MySql24eGuardException('The Sale Void MySQL tables must all use InnoDB.');
        }

        return $db;
    }

    public function test_two_concurrent_voids_restore_the_same_sale_exactly_once(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parent = null;
        $parentTransactionActive = false;

        try {
            $db = $this->guardedConcurrencyConnection();
            $this->requireConcurrencySupport();
            $baseline = $this->domainCounts($db);
            $this->assertSame(0, $this->activeRegisterCount($db));
            $fixture = $this->createFixture($db, '10.000', '4.000', true);
            $this->assertSame('6.000', $this->variantStock($db, $fixture['variant_id']));
            unset($db);

            $attempts = [
                ['actor_id' => $fixture['admin_id'], 'reason' => 'Concurrent void A'],
                ['actor_id' => $fixture['second_admin_id'], 'reason' => 'Concurrent void B'],
            ];
            foreach ($attempts as $attempt) {
                [$socket, $pid] = $this->forkGuardedWorker(
                    fn ($socket, Connection $connection): int => $this->runVoidWorker(
                        $socket,
                        $connection,
                        $attempt['actor_id'],
                        $fixture['original_sale_id'],
                        $attempt['reason'],
                    ),
                );
                $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
            }

            $parent = $this->reconnectParentAfterFork();
            foreach ($workers as $worker) {
                $this->assertSame('ready', $this->readStatus($worker['socket']));
            }

            $parent->beginTransaction();
            $parentTransactionActive = true;
            $this->assertNotNull(
                $parent->table('sales')->where('id', $fixture['original_sale_id'])->lockForUpdate()->first(['id']),
            );
            foreach ($workers as $worker) {
                $this->writeStatus($worker['socket'], 'go');
            }
            foreach ($workers as $worker) {
                $this->assertSame('attempting', $this->readStatus($worker['socket']));
                $this->assertNoWorkerResult($worker['socket'], 'A Sale Void worker passed the held Sale row lock.');
            }
            $parent->commit();
            $parentTransactionActive = false;

            $results = $this->readWorkerResults($workers);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['already-voided', 'void-success'], $statuses);

            $winnerIndex = $results[0]['status'] === 'void-success' ? 0 : 1;
            $loserIndex = 1 - $winnerIndex;
            $winner = $attempts[$winnerIndex];
            $this->assertSame(['field' => 'sale'], $results[$loserIndex]['data']);
            $this->assertSame($fixture['original_sale_id'], $results[$winnerIndex]['data']['sale_id'] ?? null);

            $db = $this->guardedConcurrencyConnection();
            $sale = $db->table('sales')->where('id', $fixture['original_sale_id'])->first();
            $this->assertNotNull($sale);
            $this->assertSame(Sale::STATUS_VOIDED, $sale->status);
            $this->assertSame($winner['actor_id'], (int) $sale->voided_by);
            $this->assertSame($winner['reason'], $sale->void_reason);
            $this->assertNotNull($sale->voided_at);
            $this->assertSame('10.000', $this->variantStock($db, $fixture['variant_id']));

            $voidMovements = $this->voidMovements($db, $fixture['original_item_id']);
            $this->assertCount(1, $voidMovements);
            $this->assertMovement($voidMovements[0], '6.000', '4.000', '10.000');
            $this->assertSame($fixture['original_item_id'], (int) $voidMovements[0]->sale_item_id);
            $this->assertSame($fixture['variant_id'], (int) $voidMovements[0]->product_variant_id);
            $this->assertSame($winner['actor_id'], (int) $voidMovements[0]->performed_by);
            $this->assertSame($winner['reason'], $voidMovements[0]->reason);
            $this->assertSame(
                0,
                $db->table('stock_movements')
                    ->where('sale_item_id', $fixture['original_item_id'])
                    ->where('movement_type', StockMovement::TYPE_SALE_VOID)
                    ->where('performed_by', $attempts[$loserIndex]['actor_id'])
                    ->where('reason', $attempts[$loserIndex]['reason'])
                    ->count(),
            );

            $this->assertVoidAudit($db, $fixture['original_sale_id'], $winner['actor_id'], $attempts);
            $this->assertSessionUnchanged($db, $fixture);
        } finally {
            $this->finishScenario($workers, $parent, $fixture, $baseline, $parentTransactionActive);
        }
    }

    public function test_checkout_and_void_serialize_stock_movements_without_lost_update(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parent = null;
        $parentTransactionActive = false;
        $checkoutToken = Str::uuid()->toString();
        $voidReason = 'Concurrent checkout reversal';

        try {
            $db = $this->guardedConcurrencyConnection();
            $this->requireConcurrencySupport();
            $baseline = $this->domainCounts($db);
            $this->assertSame(0, $this->activeRegisterCount($db));
            $fixture = $this->createFixture($db, '14.000', '4.000', false);
            $this->assertSame('10.000', $this->variantStock($db, $fixture['variant_id']));
            unset($db);

            [$voidSocket, $voidPid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runVoidWorker(
                    $socket,
                    $connection,
                    $fixture['admin_id'],
                    $fixture['original_sale_id'],
                    $voidReason,
                ),
            );
            $workers[] = ['socket' => $voidSocket, 'pid' => $voidPid, 'joined' => false];
            [$checkoutSocket, $checkoutPid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runCheckoutWorker(
                    $socket,
                    $connection,
                    $fixture['checkout_actor_id'],
                    $fixture['variant_id'],
                    $checkoutToken,
                    '3.000',
                ),
            );
            $workers[] = ['socket' => $checkoutSocket, 'pid' => $checkoutPid, 'joined' => false];

            $parent = $this->reconnectParentAfterFork();
            foreach ($workers as $worker) {
                $this->assertSame('ready', $this->readStatus($worker['socket']));
            }

            $parent->beginTransaction();
            $parentTransactionActive = true;
            $this->assertNotNull(
                $parent->table('product_variants')->where('id', $fixture['variant_id'])->lockForUpdate()->first(['id']),
            );
            foreach ($workers as $worker) {
                $this->writeStatus($worker['socket'], 'go');
            }
            foreach ($workers as $worker) {
                $this->assertSame('attempting', $this->readStatus($worker['socket']));
                $this->assertNoWorkerResult($worker['socket'], 'A worker passed the held ProductVariant row lock.');
            }
            $parent->commit();
            $parentTransactionActive = false;

            $results = $this->readWorkerResults($workers);
            $this->assertSame('void-success', $results[0]['status']);
            $this->assertSame('checkout-success', $results[1]['status']);
            $newSaleId = $results[1]['data']['sale_id'] ?? null;
            $this->assertIsInt($newSaleId);

            $db = $this->guardedConcurrencyConnection();
            $originalSale = $db->table('sales')->where('id', $fixture['original_sale_id'])->first();
            $newSale = $db->table('sales')->where('id', $newSaleId)->first();
            $this->assertNotNull($originalSale);
            $this->assertNotNull($newSale);
            $this->assertSame(Sale::STATUS_VOIDED, $originalSale->status);
            $this->assertSame($fixture['admin_id'], (int) $originalSale->voided_by);
            $this->assertSame($voidReason, $originalSale->void_reason);
            $this->assertSame(Sale::STATUS_COMPLETED, $newSale->status);
            $this->assertSame($fixture['checkout_actor_id'], (int) $newSale->recorded_by);
            $this->assertSame($fixture['session_id'], (int) $newSale->cash_register_session_id);
            $this->assertSame('11.000', $this->variantStock($db, $fixture['variant_id']));

            $newItem = $db->table('sale_items')->where('sale_id', $newSaleId)->sole();
            $this->assertSame($fixture['variant_id'], (int) $newItem->product_variant_id);
            $this->assertSame('3.000', (string) $newItem->quantity);
            $saleMovements = $db->table('stock_movements')
                ->where('sale_item_id', $newItem->id)
                ->where('movement_type', StockMovement::TYPE_SALE)
                ->get()->all();
            $voidMovements = $this->voidMovements($db, $fixture['original_item_id']);
            $this->assertCount(1, $saleMovements);
            $this->assertCount(1, $voidMovements);
            $this->assertSame($fixture['checkout_actor_id'], (int) $saleMovements[0]->performed_by);
            $this->assertSame($fixture['admin_id'], (int) $voidMovements[0]->performed_by);
            $this->assertSame($voidReason, $voidMovements[0]->reason);
            $this->assertSame('-3.000', (string) $saleMovements[0]->quantity_change);
            $this->assertSame('4.000', (string) $voidMovements[0]->quantity_change);
            $this->assertMovementBalanced($saleMovements[0]);
            $this->assertMovementBalanced($voidMovements[0]);
            $this->assertValidCheckoutVoidSerialOrder($saleMovements[0], $voidMovements[0]);

            $this->assertVoidAudit(
                $db,
                $fixture['original_sale_id'],
                $fixture['admin_id'],
                [['reason' => $voidReason]],
            );
            $this->assertSessionUnchanged($db, $fixture);
        } finally {
            if ($fixture !== null) {
                $fixture['sale_tokens'][] = $checkoutToken;
            }
            $this->finishScenario($workers, $parent, $fixture, $baseline, $parentTransactionActive);
        }
    }

    private function runVoidWorker(
        $socket,
        Connection $connection,
        int $actorId,
        int $saleId,
        string $reason,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $sale = Sale::query()->findOrFail($saleId);
        $this->writeStatus($socket, 'ready');
        $this->awaitGo($socket);
        $this->writeStatus($socket, 'attempting');

        try {
            $result = app(SaleVoidService::class)->execute($actor, $sale, $reason);
        } catch (ValidationException $exception) {
            $this->assertExactValidation($exception, 'sale', self::ALREADY_VOIDED_MESSAGE);
            $this->writeResult($socket, 'already-voided', ['field' => 'sale']);

            return self::CHILD_EXIT_SUCCESS;
        } catch (QueryException $exception) {
            $this->writeResult($socket, 'sql-error', [
                'sql_state' => (string) ($exception->errorInfo[0] ?? $exception->getCode()),
                'driver_code' => (int) ($exception->errorInfo[1] ?? 0),
            ]);

            return self::CHILD_EXIT_UNEXPECTED;
        }

        $this->writeResult($socket, 'void-success', ['sale_id' => (int) $result->getKey()]);

        return self::CHILD_EXIT_SUCCESS;
    }

    private function runCheckoutWorker(
        $socket,
        Connection $connection,
        int $actorId,
        int $variantId,
        string $token,
        string $quantity,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        $this->awaitGo($socket);
        $this->writeStatus($socket, 'attempting');

        try {
            $sale = app(RecordSale::class)->execute(
                $actor,
                $token,
                '100.00',
                [$this->saleLine($variantId, $quantity)],
            );
        } catch (QueryException $exception) {
            $this->writeResult($socket, 'sql-error', [
                'sql_state' => (string) ($exception->errorInfo[0] ?? $exception->getCode()),
                'driver_code' => (int) ($exception->errorInfo[1] ?? 0),
            ]);

            return self::CHILD_EXIT_UNEXPECTED;
        }

        $this->writeResult($socket, 'checkout-success', [
            'sale_id' => (int) $sale->getKey(),
            'session_id' => (int) $sale->cash_register_session_id,
        ]);

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @return array<string, mixed> */
    private function createFixture(
        Connection $db,
        string $openingStock,
        string $originalQuantity,
        bool $secondAdmin,
    ): array {
        $marker = 'sale_void_concurrency_'.bin2hex(random_bytes(6));

        return $db->transaction(function () use ($marker, $openingStock, $originalQuantity, $secondAdmin): array {
            $admin = $this->createUser($marker.'_admin', User::ROLE_ADMIN);
            $other = $this->createUser($marker.'_other', $secondAdmin ? User::ROLE_ADMIN : 'staff');

            $category = new Category(['name' => $marker.'_category']);
            $category->status = Category::STATUS_ACTIVE;
            $category->save();

            $product = new Product(['name' => $marker.'_product']);
            $product->category_id = $category->getKey();
            $product->status = Product::STATUS_ACTIVE;
            $product->save();

            $variant = new ProductVariant([
                'size' => $marker,
                'type_series' => '',
                'thickness' => '',
                'unit' => 'piece',
                'quantity_mode' => 'whole',
                'cost_price' => '15.00',
                'selling_price' => '25.00',
                'low_stock_threshold' => '1.000',
            ]);
            $variant->product_id = $product->getKey();
            $variant->current_stock = '0.000';
            $variant->status = ProductVariant::STATUS_ACTIVE;
            $variant->save();

            app(RecordOpeningInventory::class)->execute(
                $variant,
                $admin,
                $openingStock,
                'Dedicated Sale Void concurrency fixture opening count',
            );
            $session = app(OpenCashRegister::class)->execute($admin, '40.00');
            $originalToken = Str::uuid()->toString();
            $sale = app(RecordSale::class)->execute(
                $admin,
                $originalToken,
                '100.00',
                [$this->saleLine((int) $variant->getKey(), $originalQuantity)],
            );
            $item = $sale->items->sole();
            $sessionEvidence = $session->fresh()->only([
                'id', 'opening_cash', 'opened_by', 'opened_at', 'closed_by', 'closed_at', 'active_slot',
            ]);

            return [
                'marker' => $marker,
                'user_ids' => [(int) $admin->getKey(), (int) $other->getKey()],
                'admin_id' => (int) $admin->getKey(),
                'second_admin_id' => $secondAdmin ? (int) $other->getKey() : null,
                'checkout_actor_id' => (int) $other->getKey(),
                'category_id' => (int) $category->getKey(),
                'product_id' => (int) $product->getKey(),
                'variant_id' => (int) $variant->getKey(),
                'session_id' => (int) $session->getKey(),
                'session_evidence' => $sessionEvidence,
                'original_sale_id' => (int) $sale->getKey(),
                'original_item_id' => (int) $item->getKey(),
                'sale_tokens' => [$originalToken],
            ];
        });
    }

    private function createUser(string $marker, string $role): User
    {
        $user = new User;
        $user->name = $marker;
        $user->username = $marker;
        $user->password = 'not-used-for-login';
        $user->role = $role;
        $user->status = User::STATUS_ACTIVE;
        $user->save();

        return $user;
    }

    /** @return array{product_variant_id: int, quantity: string, expected_unit_price: string} */
    private function saleLine(int $variantId, string $quantity): array
    {
        return [
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'expected_unit_price' => '25.00',
        ];
    }

    /** @param list<array{socket: mixed, pid: int, joined: bool}> $workers */
    private function readWorkerResults(array &$workers): array
    {
        $results = [];
        foreach ($workers as &$worker) {
            $results[] = $this->readResult($worker['socket']);
            $this->joinWorkerSuccessfully($worker);
        }
        unset($worker);

        return $results;
    }

    private function assertExactValidation(ValidationException $exception, string $field, string $message): void
    {
        $errors = $exception->errors();
        if (array_keys($errors) !== [$field] || ($errors[$field][0] ?? null) !== $message) {
            throw $exception;
        }
    }

    private function awaitGo($socket): void
    {
        if ($this->readStatus($socket) !== 'go') {
            throw new RuntimeException('The dedicated Sale Void worker did not receive the start barrier.');
        }
    }

    private function guardWorkerConnection(Connection $connection): void
    {
        if ($connection->getName() !== self::CONNECTION
            || $connection->getDatabaseName() !== self::DATABASE) {
            throw new MySql24eGuardException('The dedicated Sale Void worker connection is not exact.');
        }
    }

    /** @return array<string, int> */
    private function domainCounts(Connection $connection): array
    {
        $counts = [];
        foreach (self::DOMAIN_TABLES as $table) {
            $counts[$table] = $connection->table($table)->count();
        }

        return $counts;
    }

    private function activeRegisterCount(Connection $connection): int
    {
        return $connection->table('cash_register_sessions')->where('active_slot', 1)->count();
    }

    private function variantStock(Connection $connection, int $variantId): string
    {
        return (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock');
    }

    /** @return list<object> */
    private function voidMovements(Connection $connection, int $saleItemId): array
    {
        return $connection->table('stock_movements')
            ->where('sale_item_id', $saleItemId)
            ->where('movement_type', StockMovement::TYPE_SALE_VOID)
            ->orderBy('id')->get()->all();
    }

    private function assertMovement(object $movement, string $before, string $change, string $after): void
    {
        $this->assertSame($before, (string) $movement->quantity_before);
        $this->assertSame($change, (string) $movement->quantity_change);
        $this->assertSame($after, (string) $movement->quantity_after);
    }

    private function assertMovementBalanced(object $movement): void
    {
        $this->assertSame(
            0,
            bccomp(
                bcadd((string) $movement->quantity_before, (string) $movement->quantity_change, 3),
                (string) $movement->quantity_after,
                3,
            ),
        );
    }

    private function assertValidCheckoutVoidSerialOrder(object $sale, object $void): void
    {
        $checkoutFirst = (string) $sale->quantity_before === '10.000'
            && (string) $sale->quantity_after === '7.000'
            && (string) $void->quantity_before === '7.000'
            && (string) $void->quantity_after === '11.000';
        $voidFirst = (string) $void->quantity_before === '10.000'
            && (string) $void->quantity_after === '14.000'
            && (string) $sale->quantity_before === '14.000'
            && (string) $sale->quantity_after === '11.000';

        $this->assertTrue($checkoutFirst || $voidFirst, 'The movements do not form either valid serial order.');
    }

    /** @param list<array<string, mixed>> $attempts */
    private function assertVoidAudit(Connection $db, int $saleId, int $actorId, array $attempts): void
    {
        $audits = $db->table('audit_logs')
            ->where('action', 'SALE_VOIDED')
            ->where('entity_type', 'sale')
            ->where('entity_id', $saleId)
            ->get();
        $this->assertCount(1, $audits);
        $audit = $audits->sole();
        $this->assertSame($actorId, (int) $audit->user_id);
        $this->assertSame(['status' => Sale::STATUS_COMPLETED], json_decode($audit->before_values, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['status' => Sale::STATUS_VOIDED], json_decode($audit->after_values, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame("Sale #{$saleId} was voided.", $audit->description);

        $privateAudit = json_encode([
            'before_values' => $audit->before_values,
            'after_values' => $audit->after_values,
            'description' => $audit->description,
        ], JSON_THROW_ON_ERROR);
        foreach ($attempts as $attempt) {
            $this->assertStringNotContainsString($attempt['reason'], $privateAudit);
        }
    }

    /** @param array<string, mixed> $fixture */
    private function assertSessionUnchanged(Connection $db, array $fixture): void
    {
        $session = $db->table('cash_register_sessions')->where('id', $fixture['session_id'])->first();
        $this->assertNotNull($session);
        $actual = [
            'id' => (int) $session->id,
            'opening_cash' => (string) $session->opening_cash,
            'opened_by' => (int) $session->opened_by,
            'opened_at' => (string) $session->opened_at,
            'closed_by' => $session->closed_by,
            'closed_at' => $session->closed_at,
            'active_slot' => (int) $session->active_slot,
        ];
        $expected = $fixture['session_evidence'];
        $expected['id'] = (int) $expected['id'];
        $expected['opening_cash'] = (string) $expected['opening_cash'];
        $expected['opened_by'] = (int) $expected['opened_by'];
        $expected['opened_at'] = (string) $expected['opened_at'];
        $expected['active_slot'] = (int) $expected['active_slot'];
        $this->assertSame($expected, $actual);
        $this->assertSame(1, $this->activeRegisterCount($db));
    }

    /**
     * @param  list<array{socket: mixed, pid: int, joined: bool}>  $workers
     * @param  array<string, mixed>|null  $fixture
     * @param  array<string, int>|null  $baseline
     */
    private function finishScenario(
        array &$workers,
        ?Connection $parent,
        ?array $fixture,
        ?array $baseline,
        bool $parentTransactionActive,
    ): void {
        $firstFailure = null;
        $allWorkersResolved = true;
        $parentTransactionResolved = true;

        try {
            if ($parent !== null && ($parentTransactionActive || $parent->transactionLevel() > 0)) {
                $parent->rollBack();
            }
        } catch (Throwable $exception) {
            $firstFailure = $exception;
            $parentTransactionResolved = false;
        }

        foreach ($workers as &$worker) {
            if (is_resource($worker['socket'])) {
                $this->closeSocket($worker['socket']);
                $worker['socket'] = null;
            }
        }
        unset($worker);

        foreach ($workers as &$worker) {
            if ($worker['joined']) {
                continue;
            }
            try {
                $this->joinWorkerSuccessfully($worker);
            } catch (AssertionFailedError $exception) {
                $firstFailure ??= $exception;
            } catch (Throwable $exception) {
                $firstFailure ??= $exception;
                $allWorkersResolved = false;
            }
        }
        unset($worker);

        if (! $allWorkersResolved || ! $parentTransactionResolved) {
            throw $firstFailure ?? new RuntimeException('The Sale Void scenario could not reach a safe cleanup boundary.');
        }

        if ($fixture !== null) {
            $this->cleanupFixture($fixture);
            if ($baseline !== null) {
                $this->assertSame($baseline, $this->domainCounts($this->guardedConcurrencyConnection()));
            }
        }

        if ($firstFailure !== null) {
            throw $firstFailure;
        }
    }

    /** @param array{socket: mixed, pid: int, joined: bool} $worker */
    private function joinWorkerSuccessfully(array &$worker): void
    {
        try {
            $this->waitForSuccessfulChild($worker['pid']);
            $worker['joined'] = true;
        } catch (AssertionFailedError $exception) {
            $worker['joined'] = true;
            throw $exception;
        }
    }

    /** @param array<string, mixed> $fixture */
    private function cleanupFixture(array $fixture): void
    {
        $db = $this->guardedConcurrencyConnection();
        $db->transaction(function () use ($db, $fixture): void {
            $saleIds = $db->table('sales')
                ->whereIn('checkout_token', $fixture['sale_tokens'])
                ->whereIn('recorded_by', $fixture['user_ids'])
                ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $itemIds = $saleIds === [] ? [] : $db->table('sale_items')
                ->whereIn('sale_id', $saleIds)
                ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

            if ($saleIds !== []) {
                $db->table('audit_logs')
                    ->where('action', 'SALE_VOIDED')
                    ->where('entity_type', 'sale')
                    ->whereIn('entity_id', $saleIds)
                    ->whereIn('user_id', $fixture['user_ids'])
                    ->delete();
            }
            if ($itemIds !== []) {
                $db->table('stock_movements')
                    ->whereIn('sale_item_id', $itemIds)
                    ->where('product_variant_id', $fixture['variant_id'])
                    ->delete();
                $db->table('sale_items')->whereIn('id', $itemIds)->delete();
            }
            if ($saleIds !== []) {
                $db->table('sales')->whereIn('id', $saleIds)->delete();
            }
            $db->table('cash_register_sessions')->where('id', $fixture['session_id'])->delete();
            $db->table('audit_logs')->whereIn('user_id', $fixture['user_ids'])->delete();
            $db->table('stock_movements')->where('product_variant_id', $fixture['variant_id'])->delete();
            $db->table('product_variants')->where('id', $fixture['variant_id'])->delete();
            $db->table('products')->where('id', $fixture['product_id'])->delete();
            $db->table('categories')->where('id', $fixture['category_id'])->delete();
            $db->table('users')->whereIn('id', $fixture['user_ids'])->delete();
        });
    }
}

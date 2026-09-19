<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CashRegister\CloseCashRegister;
use App\Services\CashRegister\OpenCashRegister;
use App\Services\Inventory\RecordOpeningInventory;
use App\Services\Sales\RecordSale;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Throwable;

final class CashRegister24eConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const ALREADY_OPEN_MESSAGE = 'The cash register is already open.';

    private const REGISTER_CLOSED_MESSAGE = 'The cash register is closed. Open the register before checkout.';

    private const REGISTER_TABLES = [
        'users',
        'cash_register_sessions',
        'audit_logs',
    ];

    private const CHECKOUT_TABLES = [
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

    public function test_concurrent_double_open_commits_one_global_session_and_returns_controlled_loser(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection, self::REGISTER_TABLES);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createDoubleOpenFixture($connection);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $firstActorId = $fixture['user_ids'][0];
            $secondActorId = $fixture['user_ids'][1];
            unset($connection);

            [$firstSocket, $firstPid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runOpenWorker(
                    $socket,
                    $connection,
                    $firstActorId,
                    '100.00',
                ),
            );
            $workers[] = ['socket' => $firstSocket, 'pid' => $firstPid, 'joined' => false];

            // Deliberately perform no parent DB/Eloquent operation between forks.
            [$secondSocket, $secondPid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runOpenWorker(
                    $socket,
                    $connection,
                    $secondActorId,
                    '200.00',
                ),
            );
            $workers[] = ['socket' => $secondSocket, 'pid' => $secondPid, 'joined' => false];

            $parentConnection = $this->reconnectParentAfterFork();

            $this->assertSame('ready', $this->readStatus($workers[0]['socket']));
            $this->assertSame('ready', $this->readStatus($workers[1]['socket']));
            $this->writeStatus($workers[0]['socket'], 'prepare');
            $this->writeStatus($workers[1]['socket'], 'prepare');
            $this->assertSame('at-barrier', $this->readStatus($workers[0]['socket']));
            $this->assertSame('at-barrier', $this->readStatus($workers[1]['socket']));
            $this->writeStatus($workers[0]['socket'], 'go');
            $this->writeStatus($workers[1]['socket'], 'go');

            $results = [
                $this->readResult($workers[0]['socket']),
                $this->readResult($workers[1]['socket']),
            ];
            $this->resolveScenarioAWorkers($workers, $results);

            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['already-open', 'opened'], $statuses);
            $openedResults = array_values(array_filter(
                $results,
                static fn (array $result): bool => $result['status'] === 'opened',
            ));
            $this->assertCount(1, $openedResults);
            $this->assertIsInt($openedResults[0]['data']['session_id'] ?? null);

            $parentConnection = $this->guardedConcurrencyConnection();
            $this->assertSame(1, $this->activeRegisterCount($parentConnection));
            $this->assertSame(
                1,
                $parentConnection->table('cash_register_sessions')
                    ->whereIn('opened_by', $fixture['user_ids'])
                    ->count(),
            );
            $active = $parentConnection->table('cash_register_sessions')
                ->where('active_slot', 1)
                ->first();
            $this->assertNotNull($active);
            $this->assertContains((int) $active->opened_by, $fixture['user_ids']);
            $this->assertSame(1, (int) $active->active_slot);
            $this->assertNull($active->closed_by);
            $this->assertNull($active->closed_at);
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                [],
                $baseline,
                self::REGISTER_TABLES,
            );
        }
    }

    /**
     * @param  list<array{socket: mixed, pid: int, joined: bool}>  $workers
     * @param  list<array{status: string, data: array<string, mixed>}>  $results
     */
    private function resolveScenarioAWorkers(array &$workers, array $results): void
    {
        $firstExitFailure = null;
        $invalidResult = false;
        $reportedExceptionClasses = [];
        $reportedQueryFailures = [];

        foreach ($workers as $index => &$worker) {
            $result = $results[$index];
            $expectedExitCode = self::CHILD_EXIT_SUCCESS;

            if ($result['status'] === 'query-exception') {
                $expectedExitCode = self::CHILD_EXIT_UNEXPECTED;
                $queryFailure = $this->validateScenarioAQueryFailure($result['data']);
                if ($queryFailure === null) {
                    $invalidResult = true;
                } else {
                    $reportedQueryFailures[] = [
                        'worker' => $index + 1,
                        ...$queryFailure,
                    ];
                }
            } elseif ($result['status'] === 'exception') {
                $expectedExitCode = self::CHILD_EXIT_EXCEPTION;
                $exceptionClass = $result['data']['class'] ?? null;
                if (array_keys($result['data']) !== ['class']
                    || ! is_string($exceptionClass)
                    || preg_match(
                        '/\A(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*\z/D',
                        $exceptionClass,
                    ) !== 1) {
                    $invalidResult = true;
                } else {
                    $reportedExceptionClasses[] = $exceptionClass;
                }
            } elseif (! in_array($result['status'], ['opened', 'already-open'], true)) {
                $invalidResult = true;
            }

            try {
                $this->waitForChild($worker['pid'], $expectedExitCode);
                $worker['joined'] = true;
            } catch (AssertionFailedError $exception) {
                // The base removes ownership before validating the reaped status.
                $worker['joined'] = true;
                $firstExitFailure ??= $exception;
            } catch (Throwable $exception) {
                $firstExitFailure ??= $exception;
            }
        }
        unset($worker);

        if ($firstExitFailure !== null) {
            throw $firstExitFailure;
        }
        if ($invalidResult) {
            throw new AssertionFailedError('Scenario A worker returned an invalid result safely.');
        }
        if ($reportedQueryFailures !== [] || $reportedExceptionClasses !== []) {
            $diagnostics = array_map(
                static fn (array $failure): string => sprintf(
                    'Scenario A worker %d query failure: category=%s sqlstate=%s driver_code=%s.',
                    $failure['worker'],
                    $failure['category'],
                    $failure['sqlstate'] ?? 'UNKNOWN',
                    $failure['driver_code'] === null ? 'UNKNOWN' : (string) $failure['driver_code'],
                ),
                $reportedQueryFailures,
            );
            if ($reportedExceptionClasses !== []) {
                $diagnostics[] = 'Scenario A worker failed with '
                    .implode(', ', array_unique($reportedExceptionClasses)).'.';
            }

            throw new AssertionFailedError(implode(' ', $diagnostics));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{sqlstate: string|null, driver_code: int|null, category: string}|null
     */
    private function validateScenarioAQueryFailure(array $data): ?array
    {
        if (array_keys($data) !== ['sqlstate', 'driver_code']
            || ($data['sqlstate'] !== null
                && (! is_string($data['sqlstate'])
                    || preg_match('/\A[0-9A-Z]{5}\z/D', $data['sqlstate']) !== 1))
            || ($data['driver_code'] !== null
                && (! is_int($data['driver_code'])
                    || $data['driver_code'] < 1
                    || $data['driver_code'] > 65535))) {
            return null;
        }

        return [
            'sqlstate' => $data['sqlstate'],
            'driver_code' => $data['driver_code'],
            'category' => $this->scenarioAQueryFailureCategory($data['driver_code']),
        ];
    }

    private function scenarioAQueryFailureCategory(?int $driverCode): string
    {
        return match ($driverCode) {
            1213 => 'DEADLOCK',
            1062 => 'DUPLICATE_KEY',
            1205 => 'LOCK_WAIT_TIMEOUT',
            default => 'OTHER_QUERY_EXCEPTION',
        };
    }

    public function test_checkout_holds_session_lock_until_commit_then_close_completes(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;
        $parentTransactionActive = false;
        $token = Str::uuid()->toString();

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection, self::CHECKOUT_TABLES);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createCheckoutFixture($connection, true);
            $closerId = $fixture['closer_id'];
            $this->assertIsInt($closerId);
            unset($connection);

            [$socket, $pid] = $this->forkGuardedWorker(
                fn ($childSocket, Connection $connection): int => $this->runCloseWorker(
                    $childSocket,
                    $connection,
                    $closerId,
                ),
            );
            $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];

            $parentConnection = $this->reconnectParentAfterFork();
            $this->assertSame('ready', $this->readStatus($socket));
            $parentConnection->beginTransaction();
            $parentTransactionActive = true;

            $sale = app(RecordSale::class)->execute(
                User::query()->findOrFail($fixture['cashier_id']),
                $token,
                '50.00',
                [$this->saleLine($fixture['variant_ids'][0])],
            );
            $this->assertSame($fixture['session_id'], (int) $sale->cash_register_session_id);

            $this->writeStatus($socket, 'go');
            $this->assertSame('attempting', $this->readStatus($socket));
            $this->assertNoWorkerResult(
                $socket,
                'Close completed while checkout retained the active-session lock.',
            );

            $parentConnection->commit();
            $parentTransactionActive = false;

            $result = $this->readResult($socket);
            $this->assertSame('closed', $result['status']);
            $this->assertSame($fixture['session_id'], $result['data']['session_id'] ?? null);
            $this->joinWorkerSuccessfully($workers[0]);

            $parentConnection = $this->guardedConcurrencyConnection();
            $this->assertSame(1, $parentConnection->table('sales')->where('checkout_token', $token)->count());
            $persistedSale = $parentConnection->table('sales')->where('checkout_token', $token)->first();
            $this->assertNotNull($persistedSale);
            $this->assertSame($fixture['session_id'], (int) $persistedSale->cash_register_session_id);
            $this->assertSame(1, $parentConnection->table('sale_items')->where('sale_id', $persistedSale->id)->count());
            $this->assertSame(
                1,
                $parentConnection->table('stock_movements')
                    ->join('sale_items', 'sale_items.id', '=', 'stock_movements.sale_item_id')
                    ->where('sale_items.sale_id', $persistedSale->id)
                    ->where('stock_movements.movement_type', StockMovement::TYPE_SALE)
                    ->count(),
            );
            $this->assertSame(
                '8.000',
                (string) $parentConnection->table('product_variants')
                    ->where('id', $fixture['variant_ids'][0])
                    ->value('current_stock'),
            );
            $closed = $parentConnection->table('cash_register_sessions')
                ->where('id', $fixture['session_id'])
                ->first();
            $this->assertNotNull($closed);
            $this->assertNull($closed->active_slot);
            $this->assertSame($fixture['closer_id'], (int) $closed->closed_by);
            $this->assertNotNull($closed->closed_at);
            $this->assertSame(0, $this->activeRegisterCount($parentConnection));
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                [$token],
                $baseline,
                self::CHECKOUT_TABLES,
                $parentTransactionActive,
            );
        }
    }

    public function test_close_commits_before_new_checkout_and_checkout_fails_without_mutation(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;
        $parentTransactionActive = false;
        $token = Str::uuid()->toString();

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection, self::CHECKOUT_TABLES);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createCheckoutFixture($connection, true);
            $cashierId = $fixture['cashier_id'];
            $closerId = $fixture['closer_id'];
            $variantId = $fixture['variant_ids'][0];
            $this->assertIsInt($closerId);
            unset($connection);

            [$socket, $pid] = $this->forkGuardedWorker(
                fn ($childSocket, Connection $connection): int => $this->runCheckoutWorkerExpectingClosedRegister(
                    $childSocket,
                    $connection,
                    $cashierId,
                    $variantId,
                    $token,
                ),
            );
            $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];

            $parentConnection = $this->reconnectParentAfterFork();
            $this->assertSame('ready', $this->readStatus($socket));
            $parentConnection->beginTransaction();
            $parentTransactionActive = true;
            $closed = app(CloseCashRegister::class)->execute(
                User::query()->findOrFail($closerId),
            );
            $this->assertSame($fixture['session_id'], (int) $closed->getKey());

            $this->writeStatus($socket, 'go');
            $this->assertSame('attempting', $this->readStatus($socket));
            $this->assertNoWorkerResult(
                $socket,
                'Checkout completed while close retained the active-session lock.',
            );

            $parentConnection->commit();
            $parentTransactionActive = false;

            $result = $this->readResult($socket);
            $this->assertSame('register-closed', $result['status']);
            $this->joinWorkerSuccessfully($workers[0]);

            $parentConnection = $this->guardedConcurrencyConnection();
            $persistedSession = $parentConnection->table('cash_register_sessions')
                ->where('id', $fixture['session_id'])
                ->first();
            $this->assertNotNull($persistedSession);
            $this->assertNull($persistedSession->active_slot);
            $this->assertSame($fixture['closer_id'], (int) $persistedSession->closed_by);
            $this->assertNotNull($persistedSession->closed_at);
            $this->assertSame(0, $parentConnection->table('sales')->where('checkout_token', $token)->count());
            $this->assertSame(
                0,
                $parentConnection->table('sale_items')
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->where('sales.checkout_token', $token)
                    ->count(),
            );
            $this->assertSame(
                0,
                $parentConnection->table('stock_movements')
                    ->where('product_variant_id', $fixture['variant_ids'][0])
                    ->where('movement_type', StockMovement::TYPE_SALE)
                    ->count(),
            );
            $this->assertSame(
                1,
                $parentConnection->table('stock_movements')
                    ->where('product_variant_id', $fixture['variant_ids'][0])
                    ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
                    ->count(),
            );
            $this->assertSame(
                '10.000',
                (string) $parentConnection->table('product_variants')
                    ->where('id', $fixture['variant_ids'][0])
                    ->value('current_stock'),
            );
            $this->assertSame(0, $this->activeRegisterCount($parentConnection));
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                [$token],
                $baseline,
                self::CHECKOUT_TABLES,
                $parentTransactionActive,
            );
        }
    }

    public function test_committed_checkout_token_replays_after_register_close_without_new_mutation(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $connection = null;
        $token = Str::uuid()->toString();

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection, self::CHECKOUT_TABLES);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createCheckoutFixture($connection, false);

            $sale = app(RecordSale::class)->execute(
                User::query()->findOrFail($fixture['cashier_id']),
                $token,
                '50',
                [$this->saleLine($fixture['variant_ids'][0])],
            );
            $saleId = (int) $sale->getKey();
            $itemIds = $connection->table('sale_items')
                ->where('sale_id', $saleId)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $movementIds = $connection->table('stock_movements')
                ->whereIn('sale_item_id', $itemIds)
                ->where('movement_type', StockMovement::TYPE_SALE)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $this->assertCount(1, $itemIds);
            $this->assertCount(1, $movementIds);
            $stockAfterFirstCheckout = (string) $connection->table('product_variants')
                ->where('id', $fixture['variant_ids'][0])
                ->value('current_stock');

            app(CloseCashRegister::class)->execute(
                User::query()->findOrFail($fixture['cashier_id']),
            );
            $this->assertNull(
                $connection->table('cash_register_sessions')
                    ->where('id', $fixture['session_id'])
                    ->value('active_slot'),
            );
            $sessionIdsBeforeReplay = $connection->table('cash_register_sessions')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $replayed = app(RecordSale::class)->execute(
                User::query()->findOrFail($fixture['cashier_id']),
                $token,
                '50.00',
                [[
                    'product_variant_id' => $fixture['variant_ids'][0],
                    'quantity' => '2.000',
                    'expected_unit_price' => '25.00',
                ]],
            );
            $sessionIdsAfterReplay = $connection->table('cash_register_sessions')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $this->assertSame($saleId, (int) $replayed->getKey());
            $this->assertSame(
                $sessionIdsBeforeReplay,
                $sessionIdsAfterReplay,
                'Checkout-token replay must not create or replace a cash register session.',
            );
            $this->assertSame(1, $connection->table('sales')->where('checkout_token', $token)->count());
            $this->assertSame(
                $fixture['session_id'],
                (int) $connection->table('sales')
                    ->where('id', $saleId)
                    ->value('cash_register_session_id'),
            );
            $this->assertSame(
                $itemIds,
                $connection->table('sale_items')
                    ->where('sale_id', $saleId)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            );
            $this->assertSame(
                $movementIds,
                $connection->table('stock_movements')
                    ->whereIn('sale_item_id', $itemIds)
                    ->where('movement_type', StockMovement::TYPE_SALE)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            );
            $this->assertSame(
                $stockAfterFirstCheckout,
                (string) $connection->table('product_variants')
                    ->where('id', $fixture['variant_ids'][0])
                    ->value('current_stock'),
            );
            $persistedSession = $connection->table('cash_register_sessions')
                ->where('id', $fixture['session_id'])
                ->first();
            $this->assertNotNull($persistedSession);
            $this->assertNull($persistedSession->active_slot);
            $this->assertNotNull($persistedSession->closed_at);
            $this->assertSame(0, $this->activeRegisterCount($connection));
        } finally {
            $this->finishScenario(
                $workers,
                $connection,
                $fixture,
                [$token],
                $baseline,
                self::CHECKOUT_TABLES,
            );
        }
    }

    public function test_stale_snapshot_same_token_replays_after_register_close_without_new_mutation(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;
        $parentTransactionActive = false;
        $token = Str::uuid()->toString();

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection, self::CHECKOUT_TABLES);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createCheckoutFixture($connection, false);
            $cashierId = $fixture['cashier_id'];
            $variantId = $fixture['variant_ids'][0];
            $this->assertSame(0, $connection->table('sales')->where('checkout_token', $token)->count());
            $this->assertSame(
                '10.000',
                (string) $connection->table('product_variants')
                    ->where('id', $variantId)
                    ->value('current_stock'),
            );
            unset($connection);

            [$socket, $pid] = $this->forkGuardedWorker(
                fn ($childSocket, Connection $connection): int => $this->runSaleThenCloseWorker(
                    $childSocket,
                    $connection,
                    $cashierId,
                    $variantId,
                    $token,
                ),
            );
            $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];

            $parentConnection = $this->reconnectParentAfterFork();
            $this->assertSame('ready', $this->readStatus($socket));
            $parentConnection->beginTransaction();
            $parentTransactionActive = true;

            $this->assertSame(
                0,
                $parentConnection->table('sales')->where('checkout_token', $token)->count(),
                'The parent snapshot must begin before the checkout token is committed.',
            );

            $this->writeStatus($socket, 'go');
            $result = $this->readResult($socket);
            $this->assertSame('committed-and-closed', $result['status']);
            $this->assertSame(
                [
                    'sale_id',
                    'sale_session_id',
                    'sale_item_ids',
                    'sale_movement_ids',
                    'closed_session_id',
                    'stock_after_sale',
                ],
                array_keys($result['data']),
            );
            $childSaleId = $result['data']['sale_id'];
            $childSessionId = $result['data']['sale_session_id'];
            $childItemIds = $result['data']['sale_item_ids'];
            $childMovementIds = $result['data']['sale_movement_ids'];
            $this->assertIsInt($childSaleId);
            $this->assertIsInt($childSessionId);
            $this->assertSame($fixture['session_id'], $childSessionId);
            $this->assertSame($fixture['session_id'], $result['data']['closed_session_id']);
            $this->assertIsArray($childItemIds);
            $this->assertCount(1, $childItemIds);
            $this->assertIsInt($childItemIds[0] ?? null);
            $this->assertIsArray($childMovementIds);
            $this->assertCount(1, $childMovementIds);
            $this->assertIsInt($childMovementIds[0] ?? null);
            $this->assertSame('8.000', $result['data']['stock_after_sale']);
            $this->joinWorkerSuccessfully($workers[0]);

            $this->assertSame(
                0,
                $parentConnection->table('sales')->where('checkout_token', $token)->count(),
                'The parent consistent snapshot must remain stale after the child commits.',
            );
            $sessionIdsBeforeReplay = $parentConnection->table('cash_register_sessions')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $replayed = app(RecordSale::class)->execute(
                User::query()->findOrFail($cashierId),
                $token,
                '50.00',
                [$this->saleLine($variantId)],
            );
            $this->assertSame($childSaleId, (int) $replayed->getKey());
            $this->assertSame($childSessionId, (int) $replayed->cash_register_session_id);

            $parentConnection->commit();
            $parentTransactionActive = false;

            $parentConnection = $this->reconnectParentAfterFork();
            $this->assertSame(1, $parentConnection->table('sales')->where('checkout_token', $token)->count());
            $persistedSale = $parentConnection->table('sales')->where('checkout_token', $token)->first();
            $this->assertNotNull($persistedSale);
            $this->assertSame($childSaleId, (int) $persistedSale->id);
            $this->assertSame($fixture['session_id'], (int) $persistedSale->cash_register_session_id);
            $this->assertSame($childSessionId, (int) $persistedSale->cash_register_session_id);

            $persistedItemIds = $parentConnection->table('sale_items')
                ->where('sale_id', $persistedSale->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $this->assertCount(1, $persistedItemIds);
            $this->assertSame($childItemIds, $persistedItemIds);

            $persistedMovementIds = $parentConnection->table('stock_movements')
                ->whereIn('sale_item_id', $persistedItemIds)
                ->where('movement_type', StockMovement::TYPE_SALE)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $this->assertCount(1, $persistedMovementIds);
            $this->assertSame($childMovementIds, $persistedMovementIds);
            $this->assertSame(
                1,
                $parentConnection->table('stock_movements')
                    ->where('product_variant_id', $variantId)
                    ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
                    ->count(),
            );
            $this->assertSame(
                $result['data']['stock_after_sale'],
                (string) $parentConnection->table('product_variants')
                    ->where('id', $variantId)
                    ->value('current_stock'),
            );

            $sessionIdsAfterReplay = $parentConnection->table('cash_register_sessions')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $this->assertSame(
                $sessionIdsBeforeReplay,
                $sessionIdsAfterReplay,
                'Stale-snapshot replay must not create or replace a cash register session.',
            );
            $persistedSession = $parentConnection->table('cash_register_sessions')
                ->where('id', $fixture['session_id'])
                ->first();
            $this->assertNotNull($persistedSession);
            $this->assertNull($persistedSession->active_slot);
            $this->assertSame($cashierId, (int) $persistedSession->closed_by);
            $this->assertNotNull($persistedSession->closed_at);
            $this->assertSame(0, $this->activeRegisterCount($parentConnection));
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                [$token],
                $baseline,
                self::CHECKOUT_TABLES,
                $parentTransactionActive,
            );
        }
    }

    /** @param resource $socket */
    private function runOpenWorker(
        $socket,
        Connection $connection,
        int $actorId,
        string $openingCash,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        if ($this->readStatus($socket) !== 'prepare') {
            throw new RuntimeException('The open worker did not receive the prepare barrier.');
        }
        $this->writeStatus($socket, 'at-barrier');
        if ($this->readStatus($socket) !== 'go') {
            throw new RuntimeException('The open worker did not receive the start barrier.');
        }

        try {
            $session = app(OpenCashRegister::class)->execute($actor, $openingCash);
        } catch (QueryException $exception) {
            $this->writeResult($socket, 'query-exception', $this->safeScenarioAQueryFailure($exception));

            return self::CHILD_EXIT_UNEXPECTED;
        } catch (ValidationException $exception) {
            $message = $exception->errors()['opening_cash'][0] ?? null;
            if ($message !== self::ALREADY_OPEN_MESSAGE) {
                throw $exception;
            }

            $this->writeResult($socket, 'already-open');

            return self::CHILD_EXIT_SUCCESS;
        }

        $this->writeResult($socket, 'opened', ['session_id' => (int) $session->getKey()]);

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @return array{sqlstate: string|null, driver_code: int|null} */
    private function safeScenarioAQueryFailure(QueryException $exception): array
    {
        $sqlStateCandidate = is_array($exception->errorInfo)
            ? ($exception->errorInfo[0] ?? null)
            : null;
        $sqlState = is_string($sqlStateCandidate)
            && preg_match('/\A[0-9A-Z]{5}\z/D', $sqlStateCandidate) === 1
                ? $sqlStateCandidate
                : null;

        $driverCodeCandidate = is_array($exception->errorInfo)
            ? ($exception->errorInfo[1] ?? null)
            : null;
        $driverCode = null;
        if (is_int($driverCodeCandidate)) {
            $driverCode = $driverCodeCandidate;
        } elseif (is_string($driverCodeCandidate)
            && preg_match('/\A[1-9][0-9]{0,4}\z/D', $driverCodeCandidate) === 1) {
            $driverCode = (int) $driverCodeCandidate;
        }
        if ($driverCode !== null && ($driverCode < 1 || $driverCode > 65535)) {
            $driverCode = null;
        }

        return [
            'sqlstate' => $sqlState,
            'driver_code' => $driverCode,
        ];
    }

    /** @param resource $socket */
    private function runCloseWorker($socket, Connection $connection, int $actorId): int
    {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        if ($this->readStatus($socket) !== 'go') {
            throw new RuntimeException('The close worker did not receive the start barrier.');
        }
        $this->writeStatus($socket, 'attempting');
        $session = app(CloseCashRegister::class)->execute($actor);
        $this->writeResult($socket, 'closed', ['session_id' => (int) $session->getKey()]);

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @param resource $socket */
    private function runCheckoutWorkerExpectingClosedRegister(
        $socket,
        Connection $connection,
        int $actorId,
        int $variantId,
        string $token,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        if ($this->readStatus($socket) !== 'go') {
            throw new RuntimeException('The checkout worker did not receive the start barrier.');
        }
        $this->writeStatus($socket, 'attempting');

        try {
            $sale = app(RecordSale::class)->execute(
                $actor,
                $token,
                '50.00',
                [$this->saleLine($variantId)],
            );
            $this->writeResult($socket, 'unexpected-sale', ['sale_id' => (int) $sale->getKey()]);

            return self::CHILD_EXIT_UNEXPECTED;
        } catch (ValidationException $exception) {
            $message = $exception->errors()['register'][0] ?? null;
            if ($message !== self::REGISTER_CLOSED_MESSAGE) {
                throw $exception;
            }

            $this->writeResult($socket, 'register-closed');

            return self::CHILD_EXIT_SUCCESS;
        }
    }

    /** @param resource $socket */
    private function runSaleThenCloseWorker(
        $socket,
        Connection $connection,
        int $actorId,
        int $variantId,
        string $token,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        if ($this->readStatus($socket) !== 'go') {
            throw new RuntimeException('The sale-then-close worker did not receive the start barrier.');
        }

        $sale = app(RecordSale::class)->execute(
            $actor,
            $token,
            '50.00',
            [$this->saleLine($variantId)],
        );
        $saleId = (int) $sale->getKey();
        $itemIds = $connection->table('sale_items')
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $movementIds = $connection->table('stock_movements')
            ->whereIn('sale_item_id', $itemIds)
            ->where('movement_type', StockMovement::TYPE_SALE)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $stockAfterSale = (string) $connection->table('product_variants')
            ->where('id', $variantId)
            ->value('current_stock');

        $closed = app(CloseCashRegister::class)->execute($actor);
        $this->writeResult($socket, 'committed-and-closed', [
            'sale_id' => $saleId,
            'sale_session_id' => (int) $sale->cash_register_session_id,
            'sale_item_ids' => $itemIds,
            'sale_movement_ids' => $movementIds,
            'closed_session_id' => (int) $closed->getKey(),
            'stock_after_sale' => $stockAfterSale,
        ]);

        return self::CHILD_EXIT_SUCCESS;
    }

    private function guardWorkerConnection(Connection $connection): void
    {
        if ($connection->getName() !== self::CONNECTION
            || $connection->getDatabaseName() !== self::DATABASE) {
            throw new MySql24eGuardException('The concrete #24E worker connection is not exact.');
        }
    }

    /** @return array<string, mixed> */
    private function createDoubleOpenFixture(Connection $connection): array
    {
        $marker = $this->fixtureMarker();

        return $connection->transaction(function () use ($marker): array {
            $first = $this->createUser($marker.'_first', 'staff');
            $second = $this->createUser($marker.'_second', 'staff');

            return [
                'marker' => $marker,
                'user_ids' => [(int) $first->getKey(), (int) $second->getKey()],
                'category_ids' => [],
                'product_ids' => [],
                'variant_ids' => [],
                'session_ids' => [],
            ];
        });
    }

    /** @return array<string, mixed> */
    private function createCheckoutFixture(Connection $connection, bool $withAdminCloser): array
    {
        $marker = $this->fixtureMarker();

        return $connection->transaction(function () use ($marker, $withAdminCloser): array {
            $cashier = $this->createUser($marker.'_cashier', User::ROLE_ADMIN);
            $closer = $withAdminCloser
                ? $this->createUser($marker.'_closer', User::ROLE_ADMIN)
                : null;

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
                $cashier,
                '10',
                '24E concurrency fixture opening count',
            );
            $session = app(OpenCashRegister::class)->execute($cashier, '0.00');
            $userIds = [(int) $cashier->getKey()];
            if ($closer !== null) {
                $userIds[] = (int) $closer->getKey();
            }

            return [
                'marker' => $marker,
                'user_ids' => $userIds,
                'category_ids' => [(int) $category->getKey()],
                'product_ids' => [(int) $product->getKey()],
                'variant_ids' => [(int) $variant->getKey()],
                'session_ids' => [(int) $session->getKey()],
                'cashier_id' => (int) $cashier->getKey(),
                'closer_id' => $closer === null ? null : (int) $closer->getKey(),
                'session_id' => (int) $session->getKey(),
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

    private function fixtureMarker(): string
    {
        return '24e_concurrency_'.bin2hex(random_bytes(6));
    }

    /** @return array{product_variant_id: int, quantity: string, expected_unit_price: string} */
    private function saleLine(int $variantId): array
    {
        return [
            'product_variant_id' => $variantId,
            'quantity' => '2',
            'expected_unit_price' => '25.00',
        ];
    }

    /** @param list<string> $tables @return array<string, int> */
    private function domainCounts(Connection $connection, array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = $connection->table($table)->count();
        }

        return $counts;
    }

    private function activeRegisterCount(Connection $connection): int
    {
        return $connection->table('cash_register_sessions')->where('active_slot', 1)->count();
    }

    /**
     * @param  list<array{socket: mixed, pid: int, joined: bool}>  $workers
     * @param  array<string, mixed>|null  $fixture
     * @param  list<string>  $tokens
     * @param  array<string, int>|null  $baseline
     * @param  list<string>  $tables
     */
    private function finishScenario(
        array &$workers,
        ?Connection $parentConnection,
        ?array $fixture,
        array $tokens,
        ?array $baseline,
        array $tables,
        bool $parentTransactionActive = false,
    ): void {
        $firstFailure = null;
        $allWorkersResolved = true;
        $parentTransactionResolved = true;

        try {
            if ($parentConnection !== null
                && ($parentTransactionActive || $parentConnection->transactionLevel() > 0)) {
                $parentConnection->rollBack();
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
            throw $firstFailure ?? new RuntimeException('The #24E scenario could not reach a safe cleanup boundary.');
        }

        if ($fixture !== null) {
            $this->cleanupFixture($fixture, $tokens);
            if ($baseline !== null) {
                $this->assertSame(
                    $baseline,
                    $this->domainCounts($this->guardedConcurrencyConnection(), $tables),
                );
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
            // The base removes ownership before validating status, so this PID is reaped.
            $worker['joined'] = true;
            throw $exception;
        }
    }

    /** @param array<string, mixed> $fixture @param list<string> $tokens */
    private function cleanupFixture(array $fixture, array $tokens): void
    {
        $connection = $this->guardedConcurrencyConnection();
        $connection->transaction(function () use ($connection, $fixture, $tokens): void {
            $userIds = $fixture['user_ids'];
            $variantIds = $fixture['variant_ids'];
            $saleIds = [];
            if ($tokens !== []) {
                $saleIds = $connection->table('sales')
                    ->whereIn('checkout_token', $tokens)
                    ->whereIn('recorded_by', $userIds)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
            }

            $sessionIds = $connection->table('cash_register_sessions')
                ->where(function ($query) use ($userIds): void {
                    $query->whereIn('opened_by', $userIds)
                        ->orWhereIn('closed_by', $userIds);
                })
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $sessionIds = array_values(array_unique(array_merge(
                $sessionIds,
                $fixture['session_ids'],
            )));

            if ($variantIds !== []) {
                $connection->table('stock_movements')
                    ->whereIn('product_variant_id', $variantIds)
                    ->delete();
            }
            if ($saleIds !== []) {
                $connection->table('sale_items')->whereIn('sale_id', $saleIds)->delete();
                $connection->table('sales')->whereIn('id', $saleIds)->delete();
            }
            if ($sessionIds !== []) {
                $connection->table('cash_register_sessions')->whereIn('id', $sessionIds)->delete();
            }
            $connection->table('audit_logs')->whereIn('user_id', $userIds)->delete();
            if ($variantIds !== []) {
                $connection->table('product_variants')->whereIn('id', $variantIds)->delete();
            }
            if ($fixture['product_ids'] !== []) {
                $connection->table('products')->whereIn('id', $fixture['product_ids'])->delete();
            }
            if ($fixture['category_ids'] !== []) {
                $connection->table('categories')->whereIn('id', $fixture['category_ids'])->delete();
            }
            $connection->table('users')->whereIn('id', $userIds)->delete();
        });
    }
}

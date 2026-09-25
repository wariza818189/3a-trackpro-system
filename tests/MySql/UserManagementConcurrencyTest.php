<?php

namespace Tests\MySql;

use App\Models\User;
use App\Services\Users\UserManagementService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Throwable;

final class UserManagementConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const TABLES = [
        'audit_logs', 'cash_register_sessions', 'categories', 'migrations', 'product_variants',
        'products', 'purchase_order_item_transfers', 'purchase_order_items', 'purchase_orders',
        'restock_damage_items', 'restock_items', 'restocks', 'sale_items', 'sales', 'stock_movements', 'users',
    ];

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

    protected function guardedConcurrencyConnection(): Connection
    {
        // The shared concurrency harness ledger predates the later procurement migrations.
        $db = $this->guardedMySql24eConnection();
        if ($db->getName() !== self::CONNECTION
            || $db->scalar('SELECT DATABASE()') !== self::DATABASE
            || (int) $db->scalar('SELECT @@autocommit') !== 1
            || strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))) !== 'REPEATABLE-READ') {
            throw new MySql24eGuardException('The User Management MySQL session is not exact.');
        }

        $tables = array_map(static fn (object $row): string => (string) $row->name, $db->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [self::DATABASE, 'BASE TABLE'],
        ));
        $migrations = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($tables !== self::TABLES || $migrations !== self::MIGRATIONS) {
            throw new MySql24eGuardException('The User Management MySQL table inventory or migration ledger is not exact.');
        }

        $engines = $db->select(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
            [self::DATABASE, 'BASE TABLE'],
        );
        if (count($engines) !== count(self::TABLES)
            || array_filter($engines, static fn (object $row): bool => $row->engine !== 'InnoDB') !== []) {
            throw new MySql24eGuardException('The User Management MySQL tables must all use InnoDB.');
        }

        return $db;
    }

    public function test_concurrent_cross_target_archives_preserve_one_active_admin_and_one_audit(): void
    {
        $workers = [];
        $fixture = null;
        $gate = null;
        $failure = null;
        $baseline = null;

        try {
            $db = $this->guardedConcurrencyConnection();
            $this->requireConcurrencySupport();
            $this->assertSame(0, $this->activeAdminCount($db), 'The dedicated database must start without active Admins.');
            $baseline = [$db->table('users')->count(), $db->table('audit_logs')->count()];
            $fixture = $this->createFixture($db);
            $this->assertSame(2, $this->activeAdminCount($db));

            $this->startWorker($workers, $fixture['ids'][0], $fixture['ids'][1]);
            $this->startWorker($workers, $fixture['ids'][1], $fixture['ids'][0]);
            $gate = $this->reconnectParentAfterFork();

            $this->assertSame('ready', $this->readStatus($workers[0]['socket']));
            $this->assertSame('ready', $this->readStatus($workers[1]['socket']));

            // Hold the same oldest-User mutex that the service takes. Both
            // stale actor models are loaded before either transaction proceeds.
            $gate->beginTransaction();
            $this->assertNotNull($gate->table('users')->orderBy('id')->lockForUpdate()->first(['id']));
            foreach ($workers as $worker) {
                $this->writeStatus($worker['socket'], 'go');
            }
            foreach ($workers as $worker) {
                $this->assertSame('attempting', $this->readStatus($worker['socket']));
                $this->assertNoWorkerResult($worker['socket'], 'A User Management worker passed the held mutex.');
            }
            $gate->commit();

            $results = $this->results($workers);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['archived', 'policy-rejected'], $statuses);
            $winner = $results[0]['status'] === 'archived' ? 0 : 1;
            $loser = 1 - $winner;
            $this->assertSame('actor', $results[$loser]['data']['field'] ?? null);

            $db = $this->guardedConcurrencyConnection();
            $this->assertSame(1, $this->activeAdminCount($db));
            $this->assertSame('active', $db->table('users')->where('id', $fixture['ids'][$winner])->value('status'));
            $this->assertSame('disabled', $db->table('users')->where('id', $fixture['ids'][$loser])->value('status'));
            $this->assertSame('admin', $db->table('users')->where('id', $fixture['ids'][$winner])->value('role'));
            $this->assertSame('admin', $db->table('users')->where('id', $fixture['ids'][$loser])->value('role'));

            $logs = $db->table('audit_logs')
                ->whereIn('user_id', $fixture['ids'])
                ->where('entity_type', 'user')
                ->whereIn('entity_id', $fixture['ids'])
                ->orderBy('id')->get();
            $this->assertCount(1, $logs);
            $this->assertSame('USER_DISABLED', $logs[0]->action);
            $this->assertSame($fixture['ids'][$winner], (int) $logs[0]->user_id);
            $this->assertSame($fixture['ids'][$loser], (int) $logs[0]->entity_id);
            $this->assertSame(['status' => 'active'], json_decode($logs[0]->before_values, true, flags: JSON_THROW_ON_ERROR));
            $this->assertSame(['status' => 'disabled'], json_decode($logs[0]->after_values, true, flags: JSON_THROW_ON_ERROR));
            $this->assertSame('User account disabled.', $logs[0]->description);
            $this->assertSame(0, $db->table('audit_logs')
                ->where('user_id', $fixture['ids'][$loser])
                ->where('entity_type', 'user')
                ->where('entity_id', $fixture['ids'][$winner])
                ->where('action', 'USER_DISABLED')->count());
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            if ($gate !== null && $gate->transactionLevel() > 0) {
                $gate->rollBack();
            }
            foreach ($workers as &$worker) {
                $this->closeSocket($worker['socket']);
                if (! $worker['joined']) {
                    try {
                        $this->waitForSuccessfulChild($worker['pid']);
                    } catch (Throwable $exception) {
                        $failure ??= $exception;
                    }
                }
            }
            unset($worker);
            if ($fixture !== null) {
                try {
                    $db = $this->reconnectParentAfterFork();
                    $this->cleanupFixture($db, $fixture);
                    $this->assertSame($baseline, [$db->table('users')->count(), $db->table('audit_logs')->count()]);
                    $this->assertSame(0, $this->activeAdminCount($db));
                    $this->guardedConcurrencyConnection();
                } catch (Throwable $exception) {
                    $failure ??= $exception;
                }
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /** @return array{marker: string, ids: list<int>} */
    private function createFixture(Connection $db): array
    {
        $marker = 'umc_'.bin2hex(random_bytes(8));
        $passwordHash = Hash::make(bin2hex(random_bytes(24)));

        return $db->transaction(function () use ($db, $marker, $passwordHash): array {
            $ids = [];
            foreach ([0, 1] as $index) {
                $ids[] = (int) $db->table('users')->insertGetId([
                    'name' => $marker.'_admin_'.$index,
                    'username' => $marker.'_admin_'.$index,
                    'password' => $passwordHash,
                    'role' => 'admin',
                    'status' => 'active',
                ]);
            }

            return compact('marker', 'ids');
        });
    }

    /** @param list<array{socket: mixed, pid: int, joined: bool}> $workers */
    private function startWorker(array &$workers, int $actorId, int $targetId): void
    {
        [$socket, $pid] = $this->forkGuardedWorker(
            fn ($socket, Connection $db): int => $this->archiveWorker($socket, $db, $actorId, $targetId),
        );
        $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
    }

    /** @param resource $socket */
    private function archiveWorker($socket, Connection $db, int $actorId, int $targetId): int
    {
        $this->assertSame(self::CONNECTION, $db->getName());
        $this->assertSame(self::DATABASE, $db->scalar('SELECT DATABASE()'));
        $actor = User::query()->findOrFail($actorId);
        $target = User::query()->findOrFail($targetId);
        $this->writeStatus($socket, 'ready');
        if ($this->readStatus($socket) !== 'go') {
            throw new AssertionFailedError('The User Management worker start barrier failed.');
        }
        $this->writeStatus($socket, 'attempting');

        try {
            app(UserManagementService::class)->archive($actor, $target);
            $this->writeResult($socket, 'archived');
        } catch (ValidationException $exception) {
            $this->writeResult($socket, 'policy-rejected', [
                'field' => array_key_first($exception->errors()),
            ]);
        } catch (QueryException $exception) {
            $this->writeResult($socket, 'query-failure', [
                'driver_code' => (int) ($exception->errorInfo[1] ?? 0),
            ]);
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    /**
     * @param  list<array{socket: mixed, pid: int, joined: bool}>  $workers
     * @return list<array{status: string, data: array<string, mixed>}>
     */
    private function results(array &$workers): array
    {
        $results = [];
        foreach ($workers as &$worker) {
            $result = $this->readResult($worker['socket']);
            if ($result['status'] === 'query-failure') {
                $this->fail('User Management worker query failure, driver code '.($result['data']['driver_code'] ?? 0));
            }
            if ($result['status'] === 'exception') {
                $this->fail('User Management worker exception: '.($result['data']['class'] ?? 'unknown'));
            }
            $this->waitForSuccessfulChild($worker['pid']);
            $worker['joined'] = true;
            $results[] = $result;
        }
        unset($worker);

        return $results;
    }

    /** @param array{marker: string, ids: list<int>} $fixture */
    private function cleanupFixture(Connection $db, array $fixture): void
    {
        $rows = $db->table('users')->whereIn('id', $fixture['ids'])->orderBy('id')->get(['id', 'name']);
        $this->assertCount(2, $rows);
        foreach ($rows as $index => $row) {
            $this->assertSame($fixture['ids'][$index], (int) $row->id);
            $this->assertSame($fixture['marker'].'_admin_'.$index, $row->name);
        }

        $db->transaction(function () use ($db, $fixture): void {
            $db->table('audit_logs')->whereIn('user_id', $fixture['ids'])
                ->where('entity_type', 'user')->whereIn('entity_id', $fixture['ids'])->delete();
            $db->table('users')->whereIn('id', $fixture['ids'])->delete();
        });
    }

    private function activeAdminCount(Connection $db): int
    {
        return $db->table('users')->where('role', 'admin')->where('status', 'active')->count();
    }
}

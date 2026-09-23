<?php

namespace Tests\MySql;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Procurement\CreatePurchaseOrder;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Throwable;

final class PurchaseOrder25eConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const REVISION_CONFLICT = 'This Purchase Order changed while the form was open. Review it and try again.';

    private const TOKEN_CONFLICT = 'This submission token is already associated with a different Purchase Order.';

    private const TABLES = [
        'users', 'categories', 'products', 'product_variants', 'stock_movements',
        'purchase_orders', 'purchase_order_items', 'restocks', 'restock_items', 'audit_logs',
    ];

    private const INVENTORY_TABLES = ['stock_movements', 'restocks', 'restock_items', 'audit_logs'];

    public function test_guarded_environment_and_purchase_order_schema_are_ready(): void
    {
        $connection = $this->readyConnection();
        $this->assertSame(self::DATABASE, $connection->scalar('SELECT DATABASE()'));
        $this->assertSame(1, (int) $connection->scalar('SELECT @@autocommit'));
        $this->assertSame('REPEATABLE-READ', strtoupper(str_replace('_', '-', (string) $connection->scalar('SELECT @@transaction_isolation'))));
    }

    public function test_same_purchase_order_same_revision_commits_one_complete_edit(): void
    {
        $this->scenario(function (Connection $connection, array $fixture, array &$workers, ?Connection &$gate): void {
            $a = $fixture['variants'][0];
            $po = $this->createOrder($fixture, 0, 'Initial supplier', 'Initial note', [$this->line($a, '1', '10')]);
            $id = (int) $po->getKey();
            $revision = app(UpdatePurchaseOrder::class)->revision($po);
            $proposals = [
                ['supplier' => 'First supplier', 'notes' => 'First note', 'items' => [$this->line($a, '2', '11')]],
                ['supplier' => 'Second supplier', 'notes' => 'Second note', 'items' => [$this->line($a, '3', '12')]],
            ];
            $before = $this->inventoryState($connection, $fixture);
            $this->startWorker($workers, fn ($socket, Connection $db): int => $this->editWorker($socket, $db, $fixture['users'][0], $id, $revision, $proposals[0]));
            $this->startWorker($workers, fn ($socket, Connection $db): int => $this->editWorker($socket, $db, $fixture['users'][1], $id, $revision, $proposals[1]));
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->beginTransaction();
            $gate->table('purchase_orders')->where('id', $id)->lockForUpdate()->first();
            $this->releaseWorkers($workers);
            $this->assertWorkersBlocked($workers, 'The PO header gate did not hold both stale edits.');
            $gate->commit();
            $results = $this->results($workers);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['edited', 'revision-conflict'], $statuses);
            $winner = $results[0]['status'] === 'edited' ? 0 : 1;
            $this->assertSame($id, $results[$winner]['data']['po_id'] ?? null);
            $this->assertOrder($connection, $id, $proposals[$winner], $fixture, 0);
            $this->assertSame($before, $this->inventoryState($connection, $fixture));
        });
    }

    public function test_opposite_variant_sets_complete_in_global_lock_order(): void
    {
        $this->scenario(function (Connection $connection, array $fixture, array &$workers, ?Connection &$gate): void {
            [$a, $b] = $fixture['variants'];
            $first = $this->createOrder($fixture, 0, 'PO one', null, [$this->line($a, '1', '10')]);
            $second = $this->createOrder($fixture, 1, 'PO two', null, [$this->line($b, '1', '10')]);
            $ids = [(int) $first->getKey(), (int) $second->getKey()];
            $revisions = [app(UpdatePurchaseOrder::class)->revision($first), app(UpdatePurchaseOrder::class)->revision($second)];
            $proposals = [
                ['supplier' => 'PO one edited', 'notes' => 'A then B', 'items' => [$this->line($a, '2', '11'), $this->line($b, '3', '12')]],
                ['supplier' => 'PO two edited', 'notes' => 'B then A', 'items' => [$this->line($b, '4', '13'), $this->line($a, '5', '14')]],
            ];
            $before = $this->inventoryState($connection, $fixture);
            foreach ([0, 1] as $i) {
                $this->startWorker($workers, fn ($socket, Connection $db): int => $this->editWorker($socket, $db, $fixture['users'][$i], $ids[$i], $revisions[$i], $proposals[$i]));
            }
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->beginTransaction();
            $gate->table('categories')->where('id', min($fixture['categories']))->lockForUpdate()->first();
            $this->releaseWorkers($workers);
            $this->assertWorkersBlocked($workers, 'The shared hierarchy gate did not hold both edits.');
            $gate->commit();
            $results = $this->results($workers);
            $this->assertSame(['edited', 'edited'], array_column($results, 'status'));
            foreach ([0, 1] as $i) {
                $this->assertSame($ids[$i], $results[$i]['data']['po_id'] ?? null);
                $this->assertOrder($connection, $ids[$i], $proposals[$i], $fixture, $i);
            }
            $this->assertSame($before, $this->inventoryState($connection, $fixture));
        });
    }

    public function test_same_token_same_payload_creation_returns_one_identity(): void
    {
        $this->scenario(function (Connection $connection, array $fixture, array &$workers, ?Connection &$gate): void {
            $payload = ['supplier' => 'Same supplier', 'notes' => 'Same note', 'items' => [
                $this->line($fixture['variants'][0], '2', '11'),
                $this->line($fixture['variants'][1], '3', '12'),
            ]];
            $token = $fixture['tokens'][0];
            $before = $this->inventoryState($connection, $fixture);
            foreach ([0, 1] as $i) {
                $this->startWorker($workers, fn ($socket, Connection $db): int => $this->createWorker($socket, $db, $fixture['users'][0], $token, $payload));
            }
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->beginTransaction();
            $gate->table('users')->where('id', $fixture['users'][0])->lockForUpdate()->first();
            $this->releaseWorkers($workers);
            $this->assertWorkersBlocked($workers, 'The shared creator gate did not hold both creations.');
            $gate->commit();
            $results = $this->results($workers);
            $this->assertSame(['created', 'created'], array_column($results, 'status'));
            $id = $results[0]['data']['po_id'] ?? null;
            $this->assertIsInt($id);
            $this->assertSame($id, $results[1]['data']['po_id'] ?? null);
            $this->assertSame(1, $connection->table('purchase_orders')->where('submission_token', $token)->count());
            $this->assertOrder($connection, $id, $payload, $fixture, 0);
            $this->assertSame($before, $this->inventoryState($connection, $fixture));
        });
    }

    public function test_edit_first_replay_observes_edited_state_and_rejects_old_payload(): void
    {
        $this->scenario(function (Connection $connection, array $fixture, array &$workers, ?Connection &$gate): void {
            $a = $fixture['variants'][0];
            $original = ['supplier' => 'Original supplier', 'notes' => 'Original note', 'items' => [$this->line($a, '1', '10')]];
            $edited = ['supplier' => 'Edited supplier', 'notes' => 'Edited note', 'items' => [$this->line($a, '2', '11')]];
            $po = $this->createOrder($fixture, 0, $original['supplier'], $original['notes'], $original['items']);
            $id = (int) $po->getKey();
            $revision = app(UpdatePurchaseOrder::class)->revision($po);
            $itemId = (int) $connection->table('purchase_order_items')->where('purchase_order_id', $id)->value('id');
            $before = $this->inventoryState($connection, $fixture);
            $this->startWorker($workers, fn ($socket, Connection $db): int => $this->editWorker($socket, $db, $fixture['users'][1], $id, $revision, $edited));
            $this->startWorker($workers, fn ($socket, Connection $db): int => $this->createWorker($socket, $db, $fixture['users'][0], $fixture['tokens'][0], $original));
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->beginTransaction();
            $gate->table('purchase_order_items')->where('id', $itemId)->lockForUpdate()->first();
            $this->writeStatus($workers[0]['socket'], 'go');
            $this->assertSame('attempting', $this->readStatus($workers[0]['socket']));
            $this->assertNoWorkerResult($workers[0]['socket'], 'The editor completed despite the held PO item.');
            try {
                $gate->selectOne('SELECT id FROM purchase_orders WHERE id = ? FOR UPDATE NOWAIT', [$id]);
                throw new AssertionFailedError('BLOCKED_25E_DETERMINISTIC_REPLAY_ORCHESTRATION: editor header ownership was not proven.');
            } catch (QueryException $exception) {
                $this->assertSame(3572, (int) ($exception->errorInfo[1] ?? 0), 'BLOCKED_25E_DETERMINISTIC_REPLAY_ORCHESTRATION');
            }
            $this->writeStatus($workers[1]['socket'], 'go');
            $this->assertSame('attempting', $this->readStatus($workers[1]['socket']));
            $this->assertNoWorkerResult($workers[1]['socket'], 'The replay completed before the editor released the PO header.');
            $gate->commit();
            $results = $this->results($workers);
            $this->assertSame(['edited', 'token-conflict'], array_column($results, 'status'));
            $this->assertSame($id, $results[0]['data']['po_id'] ?? null);
            $this->assertSame(1, $connection->table('purchase_orders')->where('submission_token', $fixture['tokens'][0])->count());
            $this->assertOrder($connection, $id, $edited, $fixture, 0);
            $this->assertSame($before, $this->inventoryState($connection, $fixture));
        });
    }

    private function readyConnection(): Connection
    {
        $connection = $this->guardedConcurrencyConnection();
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $this->assertSame(10, (int) $connection->scalar('SELECT @@innodb_lock_wait_timeout'));
        $engines = $connection->select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::DATABASE, 'users', 'categories', 'products', 'product_variants', 'stock_movements', 'purchase_orders', 'purchase_order_items', 'audit_logs'],
        );
        $this->assertCount(8, $engines);
        foreach ($engines as $table) {
            $this->assertSame('InnoDB', $table->engine, (string) $table->name);
        }
        $columns = $connection->select(
            'SELECT TABLE_NAME AS name, COLUMN_NAME AS column_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?) AND COLUMN_NAME IN (?, ?, ?, ?, ?, ?, ?)',
            [self::DATABASE, 'purchase_orders', 'purchase_order_items', 'submission_token', 'parent_purchase_order_id', 'created_by', 'status', 'purchase_order_id', 'product_variant_id', 'ordered_quantity'],
        );
        $actual = array_map(static fn (object $column): string => $column->name.'.'.$column->column_name, $columns);
        foreach (['purchase_orders.submission_token', 'purchase_orders.parent_purchase_order_id', 'purchase_orders.created_by', 'purchase_orders.status', 'purchase_order_items.purchase_order_id', 'purchase_order_items.product_variant_id', 'purchase_order_items.ordered_quantity'] as $required) {
            $this->assertContains($required, $actual);
        }

        return $connection;
    }

    /** @param callable(Connection, array<string, mixed>, array<int, array<string, mixed>>&, ?Connection&): void $run */
    private function scenario(callable $run): void
    {
        $workers = [];
        $fixture = null;
        $gate = null;
        $baseline = null;
        $failure = null;
        try {
            $connection = $this->readyConnection();
            $baseline = $this->counts($connection);
            $fixture = $this->fixture($connection);
            $run($connection, $fixture, $workers, $gate);
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
                $this->cleanup($fixture);
                $this->assertSame($baseline, $this->counts($this->readyConnection()), 'Fixture cleanup did not restore table counts.');
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    /** @return array<string, mixed> */
    private function fixture(Connection $connection): array
    {
        $marker = 'po25e_'.bin2hex(random_bytes(6));

        return $connection->transaction(function () use ($connection, $marker): array {
            $users = [];
            foreach ([0, 1] as $i) {
                $users[] = (int) $connection->table('users')->insertGetId([
                    'name' => $marker.'_admin_'.$i, 'username' => $marker.'_admin_'.$i,
                    'password' => 'not-used-for-login', 'role' => 'admin', 'status' => 'active',
                ]);
            }
            $categories = $products = $variants = [];
            foreach ([0, 1] as $i) {
                $categories[] = (int) $connection->table('categories')->insertGetId(['name' => $marker.'_category_'.$i, 'status' => 'active']);
                $products[] = (int) $connection->table('products')->insertGetId(['category_id' => $categories[$i], 'name' => $marker.'_product_'.$i, 'status' => 'active']);
                $variants[] = (int) $connection->table('product_variants')->insertGetId([
                    'product_id' => $products[$i], 'size' => $marker.'_'.$i, 'type_series' => '',
                    'thickness' => '', 'unit' => 'piece', 'quantity_mode' => 'whole',
                    'cost_price' => '10.00', 'selling_price' => '20.00',
                    'current_stock' => '0.000', 'low_stock_threshold' => '1.000', 'status' => 'active',
                ]);
                $connection->table('stock_movements')->insert([
                    'product_variant_id' => $variants[$i], 'movement_type' => 'INITIAL_STOCK',
                    'quantity_before' => '0.000', 'quantity_change' => '0.000', 'quantity_after' => '0.000',
                    'performed_by' => $users[0], 'reason' => 'Synthetic 25E opening inventory',
                ]);
            }

            return [
                'users' => $users, 'categories' => $categories, 'products' => $products,
                'variants' => $variants, 'tokens' => [(string) Str::uuid(), (string) Str::uuid()],
            ];
        });
    }

    /** @param array<string, mixed> $fixture @param list<array<string, mixed>> $items */
    private function createOrder(array $fixture, int $actorIndex, string $supplier, ?string $notes, array $items): PurchaseOrder
    {
        return app(CreatePurchaseOrder::class)->execute(
            User::query()->findOrFail($fixture['users'][$actorIndex]),
            $fixture['tokens'][$actorIndex], $supplier, $notes, $items,
        );
    }

    /** @return array{product_variant_id: int, ordered_quantity: string, expected_unit_cost: string} */
    private function line(int $variant, string $quantity, string $cost): array
    {
        return ['product_variant_id' => $variant, 'ordered_quantity' => $quantity, 'expected_unit_cost' => $cost];
    }

    /** @param resource $socket @param array<string, mixed> $proposal */
    private function editWorker($socket, Connection $connection, int $actorId, int $poId, string $revision, array $proposal): int
    {
        $this->workerReady($socket, $connection);
        try {
            $po = app(UpdatePurchaseOrder::class)->execute(
                User::query()->findOrFail($actorId), PurchaseOrder::query()->findOrFail($poId),
                $revision, $proposal['supplier'], $proposal['notes'], $proposal['items'],
            );
            $this->writeResult($socket, 'edited', ['po_id' => (int) $po->getKey()]);
        } catch (ValidationException $exception) {
            $this->exactConflict($exception, 'expected_revision', self::REVISION_CONFLICT);
            $this->writeResult($socket, 'revision-conflict');
        } catch (QueryException $exception) {
            $this->queryFailure($socket, $exception);

            return self::CHILD_EXIT_UNEXPECTED;
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @param resource $socket @param array<string, mixed> $payload */
    private function createWorker($socket, Connection $connection, int $actorId, string $token, array $payload): int
    {
        $this->workerReady($socket, $connection);
        try {
            $po = app(CreatePurchaseOrder::class)->execute(
                User::query()->findOrFail($actorId), $token,
                $payload['supplier'], $payload['notes'], $payload['items'],
            );
            $this->writeResult($socket, 'created', ['po_id' => (int) $po->getKey()]);
        } catch (ValidationException $exception) {
            $this->exactConflict($exception, 'submission_token', self::TOKEN_CONFLICT);
            $this->writeResult($socket, 'token-conflict');
        } catch (QueryException $exception) {
            $this->queryFailure($socket, $exception);

            return self::CHILD_EXIT_UNEXPECTED;
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @param resource $socket */
    private function workerReady($socket, Connection $connection): void
    {
        $this->assertSame(self::CONNECTION, $connection->getName());
        $this->assertSame(self::DATABASE, $connection->scalar('SELECT DATABASE()'));
        $this->assertSame(1, (int) $connection->scalar('SELECT @@autocommit'));
        $this->assertSame('REPEATABLE-READ', strtoupper(str_replace('_', '-', (string) $connection->scalar('SELECT @@transaction_isolation'))));
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $this->assertSame(10, (int) $connection->scalar('SELECT @@innodb_lock_wait_timeout'));
        $this->writeStatus($socket, 'ready');
        if ($this->readStatus($socket) !== 'go') {
            throw new AssertionFailedError('Worker start barrier failed.');
        }
        $this->writeStatus($socket, 'attempting');
    }

    /** @param resource $socket */
    private function queryFailure($socket, QueryException $exception): void
    {
        $this->writeResult($socket, 'query-failure', [
            'sqlstate' => (string) $exception->getCode(),
            'driver_code' => (int) ($exception->errorInfo[1] ?? 0),
        ]);
    }

    private function exactConflict(ValidationException $exception, string $field, string $message): void
    {
        $this->assertSame([$field => [$message]], $exception->errors());
    }

    /** @param array<int, array<string, mixed>> $workers */
    private function startWorker(array &$workers, callable $work): void
    {
        [$socket, $pid] = $this->forkGuardedWorker($work);
        $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
    }

    /** @param array<int, array<string, mixed>> $workers */
    private function readyWorkers(array $workers): void
    {
        foreach ($workers as $worker) {
            $this->assertSame('ready', $this->readStatus($worker['socket']));
        }
    }

    /** @param array<int, array<string, mixed>> $workers */
    private function releaseWorkers(array $workers): void
    {
        foreach ($workers as $worker) {
            $this->writeStatus($worker['socket'], 'go');
        }
        foreach ($workers as $worker) {
            $this->assertSame('attempting', $this->readStatus($worker['socket']));
        }
    }

    /** @param array<int, array<string, mixed>> $workers */
    private function assertWorkersBlocked(array $workers, string $message): void
    {
        foreach ($workers as $worker) {
            $this->assertNoWorkerResult($worker['socket'], $message);
        }
    }

    /** @param array<int, array<string, mixed>> $workers @return list<array{status: string, data: array<string, mixed>}> */
    private function results(array &$workers): array
    {
        $results = [];
        foreach ($workers as &$worker) {
            $result = $this->readResult($worker['socket']);
            if ($result['status'] === 'query-failure') {
                $code = $result['data']['driver_code'] ?? 0;
                $category = match ($code) {
                    1213 => 'deadlock', 1205 => 'lock timeout', default => 'database failure'
                };
                throw new AssertionFailedError('PO worker '.$category.'; driver code '.$code.'.');
            }
            if ($result['status'] === 'exception') {
                throw new AssertionFailedError('PO worker exception: '.($result['data']['class'] ?? 'unknown'));
            }
            $this->waitForSuccessfulChild($worker['pid']);
            $worker['joined'] = true;
            $results[] = $result;
        }
        unset($worker);

        return $results;
    }

    /** @param array<string, mixed> $proposal @param array<string, mixed> $fixture */
    private function assertOrder(Connection $connection, int $id, array $proposal, array $fixture, int $creatorIndex): void
    {
        $po = $connection->table('purchase_orders')->where('id', $id)->first();
        $this->assertNotNull($po);
        $this->assertSame($fixture['tokens'][$creatorIndex], $po->submission_token);
        $this->assertSame($fixture['users'][$creatorIndex], (int) $po->created_by);
        $this->assertSame('pending', $po->status);
        $this->assertNull($po->parent_purchase_order_id);
        $this->assertSame($proposal['supplier'], $po->supplier_name);
        $this->assertSame($proposal['notes'], $po->notes);
        $items = $connection->table('purchase_order_items')->where('purchase_order_id', $id)->orderBy('product_variant_id')->get();
        $expected = $proposal['items'];
        usort($expected, static fn (array $a, array $b): int => $a['product_variant_id'] <=> $b['product_variant_id']);
        $this->assertCount(count($expected), $items);
        foreach ($items as $i => $item) {
            $this->assertSame($expected[$i]['product_variant_id'], (int) $item->product_variant_id);
            $this->assertSame(number_format((float) $expected[$i]['ordered_quantity'], 3, '.', ''), (string) $item->ordered_quantity);
            $this->assertSame(number_format((float) $expected[$i]['expected_unit_cost'], 2, '.', ''), (string) $item->expected_unit_cost);
            $this->assertSame('piece', $item->unit_snapshot);
            $this->assertSame('', $item->type_series_snapshot);
            $this->assertSame('', $item->thickness_snapshot);
        }
    }

    /** @return array<string, mixed> */
    private function inventoryState(Connection $connection, array $fixture): array
    {
        $state = [];
        foreach (self::INVENTORY_TABLES as $table) {
            $state[$table] = $connection->table($table)->count();
        }
        foreach ($fixture['variants'] as $variant) {
            $state['stock_'.$variant] = (string) $connection->table('product_variants')->where('id', $variant)->value('current_stock');
            $state['movements_'.$variant] = $connection->table('stock_movements')->where('product_variant_id', $variant)->orderBy('id')->get()
                ->map(static fn (object $row): array => (array) $row)->all();
        }

        return $state;
    }

    /** @return array<string, int> */
    private function counts(Connection $connection): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            $counts[$table] = $connection->table($table)->count();
        }

        return $counts;
    }

    /** @param array<string, mixed> $fixture */
    private function cleanup(array $fixture): void
    {
        $connection = $this->readyConnection();
        $connection->transaction(function () use ($connection, $fixture): void {
            $ids = $connection->table('purchase_orders')->whereIn('submission_token', $fixture['tokens'])
                ->whereIn('created_by', $fixture['users'])->pluck('id')->all();
            if ($ids !== []) {
                $connection->table('purchase_order_items')->whereIn('purchase_order_id', $ids)->delete();
                $connection->table('purchase_orders')->whereIn('id', $ids)->delete();
            }
            $connection->table('stock_movements')->whereIn('product_variant_id', $fixture['variants'])->delete();
            $connection->table('product_variants')->whereIn('id', $fixture['variants'])->delete();
            $connection->table('products')->whereIn('id', $fixture['products'])->delete();
            $connection->table('categories')->whereIn('id', $fixture['categories'])->delete();
            $connection->table('users')->whereIn('id', $fixture['users'])->delete();
        });
    }
}

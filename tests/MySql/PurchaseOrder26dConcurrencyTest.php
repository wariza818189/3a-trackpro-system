<?php

namespace Tests\MySql;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Inventory\RecordRestock;
use App\Services\Procurement\CreatePurchaseOrder;
use App\Services\Procurement\ReceivePurchaseOrder;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Throwable;

final class PurchaseOrder26dConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const TABLES = [
        'audit_logs', 'cash_register_sessions', 'categories', 'migrations', 'product_variants',
        'products', 'purchase_order_items', 'purchase_orders', 'restock_items', 'restocks',
        'sale_items', 'sales', 'stock_movements', 'users',
    ];

    private const FIXTURE_TABLES = [
        'users', 'categories', 'products', 'product_variants', 'stock_movements',
        'purchase_orders', 'purchase_order_items', 'restocks', 'restock_items', 'audit_logs',
    ];

    protected function guardedConcurrencyConnection(): Connection
    {
        // The shared 24E identity guard remains authoritative. Its private schema
        // inventory predates #26A, so this class checks the exact extended schema.
        $db = $this->guardedMySql24eConnection();
        $this->guard26aSchema($db);

        return $db;
    }

    public function test_guarded_readiness_and_26a_schema(): void
    {
        $db = $this->readyConnection();
        $this->assertSame(self::CONNECTION, $db->getName());
        $this->assertSame(self::DATABASE, $db->scalar('SELECT DATABASE()'));
        $this->assertSame('testing', app()->environment());
        $this->assertSame(1, (int) $db->scalar('SELECT @@autocommit'));
        $this->assertSame('REPEATABLE-READ', strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))));
    }

    public function test_same_po_oversubscribed_receipts_serialize_on_current_outstanding(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            $po = $this->order($f, 0, [$this->orderLine($f['variants'][0], '5.000', '10.00')]);
            $poId = (int) $po->id;
            $item = $this->itemId($db, $poId, $f['variants'][0]);
            $tokens = [(string) Str::uuid(), (string) Str::uuid()];
            $before = $this->raceBaseline($db, $f);
            foreach ([0, 1] as $i) {
                $this->start($workers, fn ($socket, Connection $child): int => $this->receiveWorker(
                    $socket, $child, $f['users'][$i], $poId, $tokens[$i],
                    [$this->receiptLine($item, '3.125', '12.50')],
                ));
            }
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->statement('SET SESSION innodb_lock_wait_timeout = 10');
            $gate->beginTransaction();
            $gate->table('categories')->where('id', $f['category'])->lockForUpdate()->first();
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();
            $results = $this->results($workers);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['outstanding-conflict', 'received'], $statuses);
            $winner = $results[0]['status'] === 'received' ? 0 : 1;
            $this->assertReceipt($db, $poId, $item, $f['variants'][0], $tokens[$winner], '3.125', '12.50');
            $this->assertSame('partially_received', $db->table('purchase_orders')->where('id', $poId)->value('status'));
            $this->assertSame('1.875', $this->outstanding($db, $item));
            $this->assertSame('3.125', $this->stock($db, $f['variants'][0]));
            $this->assertRaceDelta($db, $f, $before, 1, 1, 1);
            $this->assertSame(0, $db->table('restocks')->where('submission_token', $tokens[1 - $winner])->count());
        });
    }

    public function test_receiver_first_blocks_then_rejects_po_edit(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            $po = $this->order($f, 0, [$this->orderLine($f['variants'][0], '5.000', '10.00')]);
            $poId = (int) $po->id;
            $item = $this->itemId($db, $poId, $f['variants'][0]);
            $revision = app(UpdatePurchaseOrder::class)->revision($po);
            $token = (string) Str::uuid();
            $before = $this->raceBaseline($db, $f);
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiveWorker(
                $socket, $child, $f['users'][0], $poId, $token,
                [$this->receiptLine($item, '2.250', '12.50')],
            ));
            $this->start($workers, fn ($socket, Connection $child): int => $this->editWorker(
                $socket, $child, $f['users'][1], $poId, $revision,
                [$this->orderLine($f['variants'][0], '6.000', '11.00')],
            ));
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->statement('SET SESSION innodb_lock_wait_timeout = 10');
            $gate->beginTransaction();
            $gate->table('purchase_order_items')->where('id', $item)->lockForUpdate()->first();
            $this->writeStatus($workers[0]['socket'], 'go');
            $this->assertSame('attempting', $this->readStatus($workers[0]['socket']));
            $this->assertNoWorkerResult($workers[0]['socket'], 'Receiver passed the held PO item.');
            try {
                $gate->selectOne('SELECT id FROM purchase_orders WHERE id = ? FOR UPDATE NOWAIT', [$poId]);
                throw new AssertionFailedError('BLOCKED_26D_DETERMINISTIC_EDIT_RECEIPT_ORCHESTRATION: receiver header ownership unproven.');
            } catch (QueryException $exception) {
                $this->assertSame(3572, (int) ($exception->errorInfo[1] ?? 0), 'BLOCKED_26D_DETERMINISTIC_EDIT_RECEIPT_ORCHESTRATION');
            }
            $this->writeStatus($workers[1]['socket'], 'go');
            $this->assertSame('attempting', $this->readStatus($workers[1]['socket']));
            $this->assertNoWorkerResult($workers[1]['socket'], 'The edit passed the receiving transaction.');
            $gate->commit();
            $this->assertSame(['received', 'edit-conflict'], array_column($this->results($workers), 'status'));
            $this->assertReceipt($db, $poId, $item, $f['variants'][0], $token, '2.250', '12.50');
            $this->assertSame('partially_received', $db->table('purchase_orders')->where('id', $poId)->value('status'));
            $this->assertSame('26D supplier', $db->table('purchase_orders')->where('id', $poId)->value('supplier_name'));
            $this->assertSame('5.000', (string) $db->table('purchase_order_items')->where('id', $item)->value('ordered_quantity'));
            $this->assertSame('2.750', $this->outstanding($db, $item));
            $this->assertSame('2.250', $this->stock($db, $f['variants'][0]));
            $this->assertRaceDelta($db, $f, $before, 1, 1, 1);
        });
    }

    public function test_receipt_and_legacy_restock_serialize_same_variant(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            $po = $this->order($f, 0, [$this->orderLine($f['variants'][0], '4.000', '10.00')]);
            $poId = (int) $po->id;
            $item = $this->itemId($db, $poId, $f['variants'][0]);
            $tokens = [(string) Str::uuid(), (string) Str::uuid()];
            $before = $this->raceBaseline($db, $f);
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiveWorker(
                $socket, $child, $f['users'][0], $poId, $tokens[0], [$this->receiptLine($item, '1.125', '12.50')],
            ));
            $this->start($workers, fn ($socket, Connection $child): int => $this->legacyWorker(
                $socket, $child, $f['users'][1], $tokens[1], $f['variants'][0], '2.375', '13.75',
            ));
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->statement('SET SESSION innodb_lock_wait_timeout = 10');
            $gate->beginTransaction();
            $gate->table('categories')->where('id', $f['category'])->lockForUpdate()->first();
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();
            $this->assertSame(['received', 'legacy-recorded'], array_column($this->results($workers), 'status'));
            $this->assertReceipt($db, $poId, $item, $f['variants'][0], $tokens[0], '1.125', '12.50');
            $legacy = $db->table('restocks')->where('submission_token', $tokens[1])->sole();
            $this->assertNull($legacy->purchase_order_id);
            $legacyItem = $db->table('restock_items')->where('restock_id', $legacy->id)->sole();
            $this->assertNull($legacyItem->purchase_order_item_id);
            $this->assertSame('2.375', (string) $legacyItem->quantity);
            $this->assertSame('13.75', (string) $legacyItem->unit_cost);
            $this->assertSame('3.500', $this->stock($db, $f['variants'][0]));
            $this->assertContains($db->table('product_variants')->where('id', $f['variants'][0])->value('cost_price'), ['12.50', '13.75']);
            $this->assertSame('2.875', $this->outstanding($db, $item));
            $this->assertRaceDelta($db, $f, $before, 2, 2, 2);
        });
    }

    public function test_two_pos_with_opposite_variant_request_orders_commit(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            [$a, $b] = $f['variants'];
            $po1 = $this->order($f, 0, [$this->orderLine($a, '2.000', '10.00'), $this->orderLine($b, '3.000', '11.00')]);
            $po2 = $this->order($f, 1, [$this->orderLine($b, '4.000', '12.00'), $this->orderLine($a, '5.000', '13.00')]);
            $id1 = (int) $po1->id;
            $id2 = (int) $po2->id;
            $lines1 = [$this->receiptLine($this->itemId($db, $id1, $a), '1.125', '14.00'), $this->receiptLine($this->itemId($db, $id1, $b), '2.250', '15.00')];
            $lines2 = [$this->receiptLine($this->itemId($db, $id2, $b), '3.125', '16.00'), $this->receiptLine($this->itemId($db, $id2, $a), '4.250', '17.00')];
            $tokens = [(string) Str::uuid(), (string) Str::uuid()];
            $before = $this->raceBaseline($db, $f);
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiveWorker($socket, $child, $f['users'][0], $id1, $tokens[0], $lines1));
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiveWorker($socket, $child, $f['users'][1], $id2, $tokens[1], $lines2));
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->statement('SET SESSION innodb_lock_wait_timeout = 10');
            $gate->beginTransaction();
            $gate->table('categories')->where('id', $f['category'])->lockForUpdate()->first();
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();
            $this->assertSame(['received', 'received'], array_column($this->results($workers), 'status'));
            $this->assertSame('5.375', $this->stock($db, $a));
            $this->assertSame('5.375', $this->stock($db, $b));
            foreach ([[$id1, $lines1, '0.875', '0.750'], [$id2, $lines2, '0.875', '0.750']] as [$id, $lines, $leftFirst, $leftSecond]) {
                $this->assertSame('partially_received', $db->table('purchase_orders')->where('id', $id)->value('status'));
                $this->assertSame($leftFirst, $this->outstanding($db, $lines[0]['purchase_order_item_id']));
                $this->assertSame($leftSecond, $this->outstanding($db, $lines[1]['purchase_order_item_id']));
            }
            $this->assertRaceDelta($db, $f, $before, 2, 4, 4);
            foreach ([$tokens[0] => [$id1, $lines1], $tokens[1] => [$id2, $lines2]] as $token => [$id, $lines]) {
                foreach ($lines as $line) {
                    $this->assertReceipt($db, $id, $line['purchase_order_item_id'], (int) $db->table('purchase_order_items')->where('id', $line['purchase_order_item_id'])->value('product_variant_id'), $token, $line['accepted_quantity'], $line['actual_unit_cost']);
                }
            }
        });
    }

    public function test_concurrent_equivalent_token_replay_after_completion(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            $po = $this->order($f, 0, [$this->orderLine($f['variants'][0], '2.125', '10.00')]);
            $poId = (int) $po->id;
            $item = $this->itemId($db, $poId, $f['variants'][0]);
            $token = (string) Str::uuid();
            $before = $this->raceBaseline($db, $f);
            foreach ([0, 1] as $ignored) {
                $this->start($workers, fn ($socket, Connection $child): int => $this->receiveWorker(
                    $socket, $child, $f['users'][0], $poId, $token,
                    [$this->receiptLine($item, '2.125', '12.50')], ' Replay ', ' Same   delivery ',
                ));
            }
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $gate->statement('SET SESSION innodb_lock_wait_timeout = 10');
            $gate->beginTransaction();
            $gate->table('users')->where('id', $f['users'][0])->lockForUpdate()->first();
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();
            $results = $this->results($workers);
            $this->assertSame(['received', 'received'], array_column($results, 'status'));
            $this->assertSame($results[0]['data']['restock_id'], $results[1]['data']['restock_id']);
            $this->assertReceipt($db, $poId, $item, $f['variants'][0], $token, '2.125', '12.50');
            $this->assertSame('completed', $db->table('purchase_orders')->where('id', $poId)->value('status'));
            $this->assertSame('0.000', $this->outstanding($db, $item));
            $this->assertSame('2.125', $this->stock($db, $f['variants'][0]));
            $this->assertRaceDelta($db, $f, $before, 1, 1, 1);
        });
    }

    private function guard26aSchema(Connection $db): void
    {
        if ($db->getName() !== self::CONNECTION || $db->scalar('SELECT DATABASE()') !== self::DATABASE
            || (int) $db->scalar('SELECT @@autocommit') !== 1
            || strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))) !== 'REPEATABLE-READ') {
            throw new MySql24eGuardException('The dedicated #26D connection is not ready.');
        }
        $tables = array_map(static fn (object $row): string => $row->name, $db->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [self::DATABASE, 'BASE TABLE'],
        ));
        if ($tables !== self::TABLES) {
            throw new MySql24eGuardException('BLOCKED_26D_SCHEMA_PRECONDITION: table inventory');
        }
        $migrations = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if (count($migrations) !== 17 || end($migrations) !== '2026_09_24_000001_add_purchase_order_links_to_restocks') {
            throw new MySql24eGuardException('BLOCKED_26D_SCHEMA_PRECONDITION: migration ledger');
        }
        $engines = $db->select('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [self::DATABASE, 'BASE TABLE']);
        if (count($engines) !== count(self::TABLES) || array_filter($engines, static fn (object $row): bool => $row->engine !== 'InnoDB') !== []) {
            throw new MySql24eGuardException('BLOCKED_26D_SCHEMA_PRECONDITION: InnoDB');
        }
        foreach ([
            ['restocks', 'purchase_order_id', 'purchase_orders', 'restocks_purchase_order_id_index'],
            ['restock_items', 'purchase_order_item_id', 'purchase_order_items', 'restock_items_purchase_order_item_id_index'],
        ] as [$table, $column, $target, $index]) {
            $definition = $db->selectOne('SELECT COLUMN_TYPE AS type, IS_NULLABLE AS nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [self::DATABASE, $table, $column]);
            $foreign = $db->selectOne('SELECT k.REFERENCED_TABLE_NAME AS target, k.REFERENCED_COLUMN_NAME AS target_column, r.DELETE_RULE AS deletion, r.UPDATE_RULE AS modification FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA = ? AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?', [self::DATABASE, $table, $column]);
            $indexed = $db->selectOne('SELECT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND INDEX_NAME = ?', [self::DATABASE, $table, $column, $index]);
            if ($definition?->type !== 'bigint unsigned' || $definition->nullable !== 'YES'
                || $foreign?->target !== $target || $foreign->target_column !== 'id'
                || $foreign->deletion !== 'RESTRICT' || $foreign->modification !== 'RESTRICT'
                || $indexed?->name !== $index) {
                throw new MySql24eGuardException('BLOCKED_26D_SCHEMA_PRECONDITION: PO link');
            }
        }
    }

    private function readyConnection(): Connection
    {
        $db = $this->guardedConcurrencyConnection();
        $db->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $this->assertSame(10, (int) $db->scalar('SELECT @@innodb_lock_wait_timeout'));

        return $db;
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
            $db = $this->readyConnection();
            $baseline = $this->counts($db);
            $fixture = $this->fixture($db);
            $run($db, $fixture, $workers, $gate);
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
                    $this->cleanup($fixture);
                    $this->assertSame($baseline, $this->counts($this->readyConnection()), 'Fixture cleanup did not restore baseline table counts.');
                } catch (Throwable $exception) {
                    $failure ??= $exception;
                }
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    /** @return array<string, mixed> */
    private function fixture(Connection $db): array
    {
        $marker = 'po26d_'.bin2hex(random_bytes(6));

        return $db->transaction(function () use ($db, $marker): array {
            $users = [];
            foreach ([0, 1] as $i) {
                $users[] = (int) $db->table('users')->insertGetId([
                    'name' => $marker.'_user_'.$i, 'username' => $marker.'_user_'.$i,
                    'password' => 'not-used-for-login', 'role' => 'admin', 'status' => 'active',
                ]);
            }
            $category = (int) $db->table('categories')->insertGetId(['name' => $marker.'_category', 'status' => 'active']);
            $product = (int) $db->table('products')->insertGetId(['category_id' => $category, 'name' => $marker.'_product', 'status' => 'active']);
            $variants = [];
            foreach ([0, 1] as $i) {
                $variants[] = (int) $db->table('product_variants')->insertGetId([
                    'product_id' => $product, 'size' => $marker.'_'.$i, 'type_series' => '', 'thickness' => '',
                    'unit' => 'kg', 'quantity_mode' => 'fractional', 'cost_price' => '10.00',
                    'selling_price' => '20.00', 'current_stock' => '0.000',
                    'low_stock_threshold' => '1.000', 'status' => 'active',
                ]);
                $db->table('stock_movements')->insert([
                    'product_variant_id' => $variants[$i], 'movement_type' => 'INITIAL_STOCK',
                    'quantity_before' => '0.000', 'quantity_change' => '0.000', 'quantity_after' => '0.000',
                    'performed_by' => $users[0], 'reason' => 'Synthetic 26D opening inventory',
                ]);
            }

            return ['users' => $users, 'category' => $category, 'product' => $product,
                'variants' => $variants, 'order_tokens' => [(string) Str::uuid(), (string) Str::uuid()]];
        });
    }

    private function order(array $f, int $creator, array $lines): PurchaseOrder
    {
        return app(CreatePurchaseOrder::class)->execute(
            User::query()->findOrFail($f['users'][$creator]), $f['order_tokens'][$creator],
            '26D supplier', null, $lines,
        );
    }

    private function orderLine(int $variant, string $quantity, string $cost): array
    {
        return ['product_variant_id' => $variant, 'ordered_quantity' => $quantity, 'expected_unit_cost' => $cost];
    }

    private function receiptLine(int $item, string $quantity, string $cost): array
    {
        return ['purchase_order_item_id' => $item, 'accepted_quantity' => $quantity, 'actual_unit_cost' => $cost];
    }

    private function itemId(Connection $db, int $po, int $variant): int
    {
        return (int) $db->table('purchase_order_items')->where('purchase_order_id', $po)->where('product_variant_id', $variant)->value('id');
    }

    /** @param resource $socket */
    private function workerReady($socket, Connection $db): void
    {
        $this->assertSame(self::CONNECTION, $db->getName());
        $this->assertSame(self::DATABASE, $db->scalar('SELECT DATABASE()'));
        $db->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $this->assertSame(10, (int) $db->scalar('SELECT @@innodb_lock_wait_timeout'));
        $this->writeStatus($socket, 'ready');
        if ($this->readStatus($socket) !== 'go') {
            throw new AssertionFailedError('Worker start barrier failed.');
        }
        $this->writeStatus($socket, 'attempting');
    }

    /** @param resource $socket */
    private function receiveWorker($socket, Connection $db, int $actor, int $po, string $token, array $lines, ?string $reference = '26D receipt', ?string $notes = null): int
    {
        $this->workerReady($socket, $db);
        try {
            $restock = app(ReceivePurchaseOrder::class)->execute(
                User::query()->findOrFail($actor), PurchaseOrder::query()->findOrFail($po),
                $token, $reference, $notes, $lines,
            );
            $this->writeResult($socket, 'received', ['restock_id' => (int) $restock->id]);
        } catch (ValidationException $exception) {
            $this->assertSame(['items.0.accepted_quantity' => ['The accepted quantity exceeds the current outstanding quantity.']], $exception->errors());
            $this->writeResult($socket, 'outstanding-conflict');
        } catch (QueryException $exception) {
            $this->writeResult($socket, 'query-failure', ['driver_code' => (int) ($exception->errorInfo[1] ?? 0)]);

            return self::CHILD_EXIT_UNEXPECTED;
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @param resource $socket */
    private function legacyWorker($socket, Connection $db, int $actor, string $token, int $variant, string $quantity, string $cost): int
    {
        $this->workerReady($socket, $db);
        try {
            $restock = app(RecordRestock::class)->execute(
                User::query()->findOrFail($actor), $token, '26D legacy', null,
                [['product_variant_id' => $variant, 'quantity' => $quantity, 'unit_cost' => $cost]],
            );
            $this->writeResult($socket, 'legacy-recorded', ['restock_id' => (int) $restock->id]);
        } catch (QueryException $exception) {
            $this->writeResult($socket, 'query-failure', ['driver_code' => (int) ($exception->errorInfo[1] ?? 0)]);

            return self::CHILD_EXIT_UNEXPECTED;
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @param resource $socket */
    private function editWorker($socket, Connection $db, int $actor, int $po, string $revision, array $lines): int
    {
        $this->workerReady($socket, $db);
        try {
            app(UpdatePurchaseOrder::class)->execute(
                User::query()->findOrFail($actor), PurchaseOrder::query()->findOrFail($po),
                $revision, 'Changed supplier', 'Changed note', $lines,
            );
            $this->writeResult($socket, 'edited');
        } catch (ValidationException $exception) {
            $this->assertSame(['purchase_order' => ['Only a pending Purchase Order may be edited.']], $exception->errors());
            $this->writeResult($socket, 'edit-conflict');
        } catch (QueryException $exception) {
            $this->writeResult($socket, 'query-failure', ['driver_code' => (int) ($exception->errorInfo[1] ?? 0)]);

            return self::CHILD_EXIT_UNEXPECTED;
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    /** @param array<int, array<string, mixed>> $workers */
    private function start(array &$workers, callable $work): void
    {
        [$socket, $pid] = $this->forkGuardedWorker($work);
        $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
    }

    private function readyWorkers(array $workers): void
    {
        foreach ($workers as $worker) {
            $this->assertSame('ready', $this->readStatus($worker['socket']));
        }
    }

    private function releaseWorkers(array $workers): void
    {
        foreach ($workers as $worker) {
            $this->writeStatus($worker['socket'], 'go');
        }
        foreach ($workers as $worker) {
            $this->assertSame('attempting', $this->readStatus($worker['socket']));
        }
    }

    private function blockedWorkers(array $workers): void
    {
        foreach ($workers as $worker) {
            $this->assertNoWorkerResult($worker['socket'], 'The contention gate did not hold the worker.');
        }
    }

    private function results(array &$workers): array
    {
        $results = [];
        foreach ($workers as &$worker) {
            $result = $this->readResult($worker['socket']);
            if ($result['status'] === 'query-failure') {
                $this->fail('26D database failure, driver code '.($result['data']['driver_code'] ?? 0));
            }
            if ($result['status'] === 'exception') {
                $this->fail('26D worker exception: '.($result['data']['class'] ?? 'unknown'));
            }
            $this->waitForSuccessfulChild($worker['pid']);
            $worker['joined'] = true;
            $results[] = $result;
        }
        unset($worker);

        return $results;
    }

    private function stock(Connection $db, int $variant): string
    {
        return (string) $db->table('product_variants')->where('id', $variant)->value('current_stock');
    }

    private function outstanding(Connection $db, int $item): string
    {
        $ordered = (string) $db->table('purchase_order_items')->where('id', $item)->value('ordered_quantity');
        $accepted = '0.000';
        foreach ($db->table('restock_items')->where('purchase_order_item_id', $item)->pluck('quantity') as $quantity) {
            $accepted = bcadd($accepted, (string) $quantity, 3);
        }
        $this->assertLessThanOrEqual(0, bccomp($accepted, $ordered, 3), 'Accepted quantity exceeded the PO order.');

        return bcsub($ordered, $accepted, 3);
    }

    private function assertReceipt(Connection $db, int $po, int $item, int $variant, string $token, string $quantity, string $cost): void
    {
        $restock = $db->table('restocks')->where('submission_token', $token)->sole();
        $this->assertSame($po, (int) $restock->purchase_order_id);
        $lines = $db->table('restock_items')->where('restock_id', $restock->id)->where('purchase_order_item_id', $item)->get();
        $this->assertCount(1, $lines);
        $line = $lines->first();
        $this->assertSame($variant, (int) $line->product_variant_id);
        $this->assertSame($quantity, (string) $line->quantity);
        $this->assertSame($cost, (string) $line->unit_cost);
        $movement = $db->table('stock_movements')->where('restock_item_id', $line->id)->sole();
        $this->assertSame('RESTOCK', $movement->movement_type);
        $this->assertSame($quantity, (string) $movement->quantity_change);
        $expectedCost = (string) $db->table('purchase_order_items')->where('id', $item)->value('expected_unit_cost');
        $this->assertContains($expectedCost, ['10.00', '11.00', '12.00', '13.00']);
    }

    private function raceBaseline(Connection $db, array $f): array
    {
        return ['restocks' => $db->table('restocks')->whereIn('recorded_by', $f['users'])->count(),
            'items' => $db->table('restock_items')->whereIn('product_variant_id', $f['variants'])->count(),
            'movements' => $db->table('stock_movements')->whereIn('product_variant_id', $f['variants'])->where('movement_type', 'RESTOCK')->count(),
            'audit' => $db->table('audit_logs')->whereIn('user_id', $f['users'])->count()];
    }

    private function assertRaceDelta(Connection $db, array $f, array $before, int $restocks, int $items, int $movements): void
    {
        $after = $this->raceBaseline($db, $f);
        $this->assertSame($before['restocks'] + $restocks, $after['restocks']);
        $this->assertSame($before['items'] + $items, $after['items']);
        $this->assertSame($before['movements'] + $movements, $after['movements']);
        $this->assertSame($before['audit'], $after['audit']);
    }

    private function counts(Connection $db): array
    {
        $counts = [];
        foreach (self::FIXTURE_TABLES as $table) {
            $counts[$table] = $db->table($table)->count();
        }

        return $counts;
    }

    private function cleanup(array $f): void
    {
        $db = $this->readyConnection();
        $db->transaction(function () use ($db, $f): void {
            $poIds = $db->table('purchase_orders')->whereIn('submission_token', $f['order_tokens'])->whereIn('created_by', $f['users'])->pluck('id')->all();
            $restockIds = $db->table('restocks')->whereIn('recorded_by', $f['users'])->pluck('id')->all();
            $db->table('stock_movements')->whereIn('product_variant_id', $f['variants'])->delete();
            $db->table('restock_items')->whereIn('restock_id', $restockIds)->delete();
            $db->table('restocks')->whereIn('id', $restockIds)->delete();
            $db->table('purchase_order_items')->whereIn('purchase_order_id', $poIds)->delete();
            $db->table('purchase_orders')->whereIn('id', $poIds)->delete();
            $db->table('audit_logs')->whereIn('user_id', $f['users'])->delete();
            $db->table('product_variants')->whereIn('id', $f['variants'])->delete();
            $db->table('products')->where('id', $f['product'])->delete();
            $db->table('categories')->where('id', $f['category'])->delete();
            $db->table('users')->whereIn('id', $f['users'])->delete();
        });
    }
}

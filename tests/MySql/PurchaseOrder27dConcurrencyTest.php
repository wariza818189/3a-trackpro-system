<?php

namespace Tests\MySql;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Procurement\CreateFollowUpPurchaseOrder;
use App\Services\Procurement\CreatePurchaseOrder;
use App\Services\Procurement\ReceivePurchaseOrder;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Throwable;

final class PurchaseOrder27dConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const TABLES = [
        'audit_logs', 'cash_register_sessions', 'categories', 'migrations', 'product_variants',
        'products', 'purchase_order_item_transfers', 'purchase_order_items', 'purchase_orders',
        'restock_items', 'restocks', 'sale_items', 'sales', 'stock_movements', 'users',
    ];

    private const FIXTURE_TABLES = [
        'users', 'categories', 'products', 'product_variants', 'stock_movements',
        'purchase_orders', 'purchase_order_items', 'purchase_order_item_transfers',
        'restocks', 'restock_items', 'audit_logs',
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
    ];

    protected function guardedConcurrencyConnection(): Connection
    {
        // The shared 24E schema inventory predates the 26A and 27A migrations.
        $db = $this->guardedMySql24eConnection();
        $this->guard27dSchema($db);

        return $db;
    }

    public function test_guarded_readiness_and_27a_transfer_schema(): void
    {
        $db = $this->readyConnection();
        $this->assertSame(self::CONNECTION, $db->getName());
        $this->assertSame(self::DATABASE, $db->scalar('SELECT DATABASE()'));
        $this->assertSame('testing', app()->environment());
        $this->assertSame(1, (int) $db->scalar('SELECT @@autocommit'));
        $this->assertSame('REPEATABLE-READ', strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))));
        $this->assertSame(10, (int) $db->scalar('SELECT @@innodb_lock_wait_timeout'));
    }

    public function test_follow_up_and_receipt_on_same_source_line_serialize_current_outstanding(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            $po = $this->order($f, [$this->orderLine($f['variants'][0], '5.000', '10.00')]);
            $item = $this->itemId($db, (int) $po->id, $f['variants'][0]);
            $followToken = (string) Str::uuid();
            $receiptToken = (string) Str::uuid();
            $before = $this->evidence($db, $f);
            $this->start($workers, fn ($socket, Connection $child): int => $this->followWorker($socket, $child, $f['users'][0], (int) $po->id, $followToken, [$this->selection($item, '11.25')]));
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiptWorker($socket, $child, $f['users'][2], (int) $po->id, $receiptToken, $item, '2.125'));
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $this->hold($gate, 'product_variants', $f['variants'][0]);
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();

            [$follow, $receipt] = $this->results($workers);
            $this->assertSame('followed', $follow['status']);
            $this->assertContains($receipt['status'], ['received', 'receipt-conflict']);
            $accepted = $receipt['status'] === 'received' ? '2.125' : '0.000';
            $transferred = bcsub('5.000', $accepted, 3);
            $this->assertTransferSet($db, (int) $po->id, (int) $follow['data']['child_id'], [$item => [$f['variants'][0], $transferred, '11.25']], $f['users'][0]);
            $this->assertCoverage($db, $item, '5.000', $accepted, $transferred);
            $this->assertSame('closed_with_remainder', $this->purchaseOrderStatus($db, (int) $po->id));
            $this->assertSame($accepted, $this->stock($db, $f['variants'][0]));
            $this->assertSame($receipt['status'] === 'received' ? '12.50' : '10.00', (string) $db->table('product_variants')->where('id', $f['variants'][0])->value('cost_price'));
            $this->assertReceiptEvidence($db, (int) $po->id, $item, $f['variants'][0], $receiptToken, $receipt['status'] === 'received');
            $this->assertEvidenceDelta($db, $f, $before, 1, 1, $receipt['status'] === 'received' ? 1 : 0);
        });
    }

    public function test_two_follow_ups_on_same_source_line_create_one_transfer(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            // B remains outstanding so the loser reaches the transferred-line guard.
            $po = $this->order($f, [$this->orderLine($f['variants'][0], '3.125', '10.00'), $this->orderLine($f['variants'][1], '1.000', '10.00')]);
            $item = $this->itemId($db, (int) $po->id, $f['variants'][0]);
            $other = $this->itemId($db, (int) $po->id, $f['variants'][1]);
            $tokens = [(string) Str::uuid(), (string) Str::uuid()];
            $before = $this->evidence($db, $f);
            foreach ([0, 1] as $i) {
                $this->start($workers, fn ($socket, Connection $child): int => $this->followWorker($socket, $child, $f['users'][$i], (int) $po->id, $tokens[$i], [$this->selection($item, '11.25')]));
            }
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $this->hold($gate, 'product_variants', $f['variants'][0]);
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();
            $results = $this->results($workers);
            $this->assertSame(['follow-conflict', 'followed'], $this->sortedStatuses($results));
            $winner = $results[0]['status'] === 'followed' ? 0 : 1;
            $this->assertTransferSet($db, (int) $po->id, (int) $results[$winner]['data']['child_id'], [$item => [$f['variants'][0], '3.125', '11.25']], $f['users'][$winner]);
            $this->assertCoverage($db, $item, '3.125', '0.000', '3.125');
            $this->assertCoverage($db, $other, '1.000', '0.000', '0.000');
            $this->assertSame('partially_received', $this->purchaseOrderStatus($db, (int) $po->id));
            $this->assertSame(0, $db->table('purchase_orders')->where('submission_token', $tokens[1 - $winner])->count());
            $this->assertUnchangedInventory($db, $f, $before);
            $this->assertEvidenceDelta($db, $f, $before, 1, 1, 0);
        });
    }

    public function test_overlapping_opposite_follow_up_selections_are_atomic(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            [$a, $b, $c] = $f['variants'];
            $po = $this->order($f, [$this->orderLine($a, '2.125', '10.00'), $this->orderLine($b, '3.375', '10.00'), $this->orderLine($c, '1.000', '10.00')]);
            $aItem = $this->itemId($db, (int) $po->id, $a);
            $bItem = $this->itemId($db, (int) $po->id, $b);
            $cItem = $this->itemId($db, (int) $po->id, $c);
            $tokens = [(string) Str::uuid(), (string) Str::uuid()];
            $before = $this->evidence($db, $f);
            $sets = [[$this->selection($aItem, '11.25'), $this->selection($bItem, '12.50')], [$this->selection($bItem, '12.50'), $this->selection($aItem, '11.25')]];
            foreach ([0, 1] as $i) {
                $this->start($workers, fn ($socket, Connection $child): int => $this->followWorker($socket, $child, $f['users'][$i], (int) $po->id, $tokens[$i], $sets[$i]));
            }
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $this->hold($gate, 'product_variants', $a);
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();
            $results = $this->results($workers);
            $this->assertSame(['follow-conflict', 'followed'], $this->sortedStatuses($results));
            $winner = $results[0]['status'] === 'followed' ? 0 : 1;
            $this->assertTransferSet($db, (int) $po->id, (int) $results[$winner]['data']['child_id'], [
                $aItem => [$a, '2.125', '11.25'], $bItem => [$b, '3.375', '12.50'],
            ], $f['users'][$winner]);
            $this->assertCoverage($db, $aItem, '2.125', '0.000', '2.125');
            $this->assertCoverage($db, $bItem, '3.375', '0.000', '3.375');
            $this->assertCoverage($db, $cItem, '1.000', '0.000', '0.000');
            $this->assertSame('partially_received', $this->purchaseOrderStatus($db, (int) $po->id));
            $this->assertSame(0, $db->table('purchase_orders')->where('submission_token', $tokens[1 - $winner])->count());
            $this->assertUnchangedInventory($db, $f, $before);
            $this->assertEvidenceDelta($db, $f, $before, 1, 2, 0);
        });
    }

    public function test_follow_up_first_blocks_then_rejects_purchase_order_edit(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            $po = $this->order($f, [$this->orderLine($f['variants'][0], '4.125', '10.00')]);
            $item = $this->itemId($db, (int) $po->id, $f['variants'][0]);
            $revision = app(UpdatePurchaseOrder::class)->revision($po);
            $before = $this->evidence($db, $f);
            $this->start($workers, fn ($socket, Connection $child): int => $this->followWorker($socket, $child, $f['users'][0], (int) $po->id, (string) Str::uuid(), [$this->selection($item, '11.25')]));
            $this->start($workers, fn ($socket, Connection $child): int => $this->editWorker($socket, $child, $f['users'][1], (int) $po->id, $revision, $f['variants'][0]));
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $this->hold($gate, 'purchase_order_items', $item);
            $this->writeStatus($workers[0]['socket'], 'go');
            $this->assertSame('attempting', $this->readStatus($workers[0]['socket']));
            $this->assertNoWorkerResult($workers[0]['socket'], 'Follow-up passed the held source item.');
            try {
                $gate->selectOne('SELECT id FROM purchase_orders WHERE id = ? FOR UPDATE NOWAIT', [(int) $po->id]);
                throw new AssertionFailedError('BLOCKED_27D_DETERMINISTIC_FOLLOWUP_EDIT_ORCHESTRATION: source header ownership unproven.');
            } catch (QueryException $exception) {
                $this->assertSame(3572, (int) ($exception->errorInfo[1] ?? 0), 'BLOCKED_27D_DETERMINISTIC_FOLLOWUP_EDIT_ORCHESTRATION');
            }
            $this->writeStatus($workers[1]['socket'], 'go');
            $this->assertSame('attempting', $this->readStatus($workers[1]['socket']));
            $this->assertNoWorkerResult($workers[1]['socket'], 'Editor passed the follow-up transaction.');
            $gate->commit();
            [$follow, $edit] = $this->results($workers);
            $this->assertSame(['followed', 'edit-conflict'], [$follow['status'], $edit['status']]);
            $this->assertTransferSet($db, (int) $po->id, (int) $follow['data']['child_id'], [$item => [$f['variants'][0], '4.125', '11.25']], $f['users'][0]);
            $this->assertSame('closed_with_remainder', $this->purchaseOrderStatus($db, (int) $po->id));
            $this->assertSame('27D supplier', $db->table('purchase_orders')->where('id', $po->id)->value('supplier_name'));
            $this->assertCoverage($db, $item, '4.125', '0.000', '4.125');
            $this->assertUnchangedInventory($db, $f, $before);
            $this->assertEvidenceDelta($db, $f, $before, 1, 1, 0);
        });
    }

    public function test_concurrent_equivalent_follow_up_token_replay_returns_one_child(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            $po = $this->order($f, [$this->orderLine($f['variants'][0], '2.125', '10.00'), $this->orderLine($f['variants'][1], '3.375', '10.00')]);
            $a = $this->itemId($db, (int) $po->id, $f['variants'][0]);
            $b = $this->itemId($db, (int) $po->id, $f['variants'][1]);
            $token = (string) Str::uuid();
            $before = $this->evidence($db, $f);
            foreach ([0, 1] as $ignored) {
                $this->start($workers, fn ($socket, Connection $child): int => $this->followWorker($socket, $child, $f['users'][0], (int) $po->id, $token, [$this->selection($a, '11.25'), $this->selection($b, '12.50')]));
            }
            $gate = $this->reconnectParentAfterFork();
            $this->readyWorkers($workers);
            $this->hold($gate, 'users', $f['users'][0]);
            $this->releaseWorkers($workers);
            $this->blockedWorkers($workers);
            $gate->commit();
            $results = $this->results($workers);
            $this->assertSame(['followed', 'followed'], array_column($results, 'status'));
            $this->assertSame($results[0]['data']['child_id'], $results[1]['data']['child_id']);
            $this->assertTransferSet($db, (int) $po->id, (int) $results[0]['data']['child_id'], [
                $a => [$f['variants'][0], '2.125', '11.25'], $b => [$f['variants'][1], '3.375', '12.50'],
            ], $f['users'][0]);
            $this->assertCoverage($db, $a, '2.125', '0.000', '2.125');
            $this->assertCoverage($db, $b, '3.375', '0.000', '3.375');
            $this->assertSame('closed_with_remainder', $this->purchaseOrderStatus($db, (int) $po->id));
            $this->assertUnchangedInventory($db, $f, $before);
            $this->assertEvidenceDelta($db, $f, $before, 1, 2, 0);
        });
    }

    private function guard27dSchema(Connection $db): void
    {
        if ($db->getName() !== self::CONNECTION || $db->scalar('SELECT DATABASE()') !== self::DATABASE
            || (int) $db->scalar('SELECT @@autocommit') !== 1
            || strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))) !== 'REPEATABLE-READ') {
            throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: dedicated session');
        }
        $tables = array_map(static fn (object $row): string => $row->name, $db->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [self::DATABASE, 'BASE TABLE'],
        ));
        if ($tables !== self::TABLES) {
            throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: table inventory');
        }
        $migrations = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($migrations !== self::MIGRATIONS) {
            throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: migration ledger');
        }
        $engines = $db->select('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [self::DATABASE, 'BASE TABLE']);
        if (count($engines) !== count(self::TABLES) || array_filter($engines, static fn (object $row): bool => $row->engine !== 'InnoDB') !== []) {
            throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: InnoDB');
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
                throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: receipt links');
            }
        }
        $quantity = $db->selectOne('SELECT COLUMN_TYPE AS type, IS_NULLABLE AS nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [self::DATABASE, 'purchase_order_item_transfers', 'quantity']);
        if ($quantity?->type !== 'decimal(14,3)' || $quantity->nullable !== 'NO') {
            throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: transfer quantity');
        }
        foreach ([
            ['source_purchase_order_item_id', 'purchase_order_items', 'po_item_transfers_source_unique'],
            ['target_purchase_order_item_id', 'purchase_order_items', 'po_item_transfers_target_unique'],
            ['created_by', 'users', null],
        ] as [$column, $target, $unique]) {
            $foreign = $db->selectOne('SELECT k.REFERENCED_TABLE_NAME AS target, k.REFERENCED_COLUMN_NAME AS target_column, r.DELETE_RULE AS deletion, r.UPDATE_RULE AS modification FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA = ? AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?', [self::DATABASE, 'purchase_order_item_transfers', $column]);
            if ($foreign?->target !== $target || $foreign->target_column !== 'id' || $foreign->deletion !== 'RESTRICT' || $foreign->modification !== 'RESTRICT') {
                throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: transfer foreign key');
            }
            if ($unique !== null) {
                $index = $db->selectOne('SELECT NON_UNIQUE AS non_unique FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? AND COLUMN_NAME = ?', [self::DATABASE, 'purchase_order_item_transfers', $unique, $column]);
                if ($index === null || (int) $index->non_unique !== 0) {
                    throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: transfer uniqueness');
                }
            }
        }
        $actorIndex = $db->select('SELECT COLUMN_NAME AS column_name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX', [self::DATABASE, 'purchase_order_item_transfers', 'po_item_transfers_actor_created_index']);
        if (array_map(static fn (object $row): string => $row->column_name, $actorIndex) !== ['created_by', 'created_at']) {
            throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: actor index');
        }
        $checks = $db->select('SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ?', [self::DATABASE, 'purchase_order_item_transfers', 'CHECK']);
        $names = array_map(static fn (object $row): string => $row->name, $checks);
        sort($names);
        if ($names !== ['po_item_transfers_distinct_items', 'po_item_transfers_quantity_positive']) {
            throw new MySql24eGuardException('BLOCKED_27D_SCHEMA_PRECONDITION: transfer checks');
        }
    }

    private function readyConnection(): Connection
    {
        $db = $this->guardedConcurrencyConnection();
        $db->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $this->assertSame(10, (int) $db->scalar('SELECT @@innodb_lock_wait_timeout'));

        return $db;
    }

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
                    $this->assertSame($baseline, $this->counts($this->readyConnection()), 'Fixture cleanup did not restore baseline counts.');
                } catch (Throwable $exception) {
                    $failure ??= $exception;
                }
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    private function fixture(Connection $db): array
    {
        $marker = 'po27d_'.bin2hex(random_bytes(6));

        return $db->transaction(function () use ($db, $marker): array {
            $users = [];
            foreach (['admin', 'admin', 'staff'] as $i => $role) {
                $users[] = (int) $db->table('users')->insertGetId([
                    'name' => $marker.'_user_'.$i, 'username' => $marker.'_user_'.$i,
                    'password' => 'not-used-for-login', 'role' => $role, 'status' => 'active',
                ]);
            }
            $category = (int) $db->table('categories')->insertGetId(['name' => $marker.'_category', 'status' => 'active']);
            $product = (int) $db->table('products')->insertGetId(['category_id' => $category, 'name' => $marker.'_product', 'status' => 'active']);
            $variants = [];
            foreach ([0, 1, 2] as $i) {
                $variants[] = (int) $db->table('product_variants')->insertGetId([
                    'product_id' => $product, 'size' => $marker.'_'.$i, 'type_series' => '', 'thickness' => '',
                    'unit' => 'kg', 'quantity_mode' => 'fractional', 'cost_price' => '10.00',
                    'selling_price' => '20.00', 'current_stock' => '0.000',
                    'low_stock_threshold' => '1.000', 'status' => 'active',
                ]);
                $db->table('stock_movements')->insert([
                    'product_variant_id' => $variants[$i], 'movement_type' => 'INITIAL_STOCK',
                    'quantity_before' => '0.000', 'quantity_change' => '0.000', 'quantity_after' => '0.000',
                    'performed_by' => $users[0], 'reason' => 'Synthetic 27D opening inventory',
                ]);
            }

            return compact('users', 'category', 'product', 'variants') + ['order_token' => (string) Str::uuid()];
        });
    }

    private function order(array $f, array $lines): PurchaseOrder
    {
        return app(CreatePurchaseOrder::class)->execute(User::query()->findOrFail($f['users'][0]), $f['order_token'], '27D supplier', null, $lines);
    }

    private function orderLine(int $variant, string $quantity, string $cost): array
    {
        return ['product_variant_id' => $variant, 'ordered_quantity' => $quantity, 'expected_unit_cost' => $cost];
    }

    private function selection(int $item, string $cost): array
    {
        return ['source_purchase_order_item_id' => $item, 'expected_unit_cost' => $cost];
    }

    private function itemId(Connection $db, int $po, int $variant): int
    {
        return (int) $db->table('purchase_order_items')->where('purchase_order_id', $po)->where('product_variant_id', $variant)->value('id');
    }

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

    private function followWorker($socket, Connection $db, int $actor, int $po, string $token, array $items): int
    {
        $this->workerReady($socket, $db);
        try {
            $child = app(CreateFollowUpPurchaseOrder::class)->execute(
                User::query()->findOrFail($actor), PurchaseOrder::query()->findOrFail($po),
                $token, '27D follow-up supplier', '27D planning note', $items,
            );
            $this->writeResult($socket, 'followed', ['child_id' => (int) $child->id]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->assertTrue(
                $errors === ['items.0.source_purchase_order_item_id' => ['This source line was already transferred.']]
                    || $errors === ['items.1.source_purchase_order_item_id' => ['This source line was already transferred.']],
                'Unexpected follow-up conflict: '.json_encode($errors),
            );
            $this->writeResult($socket, 'follow-conflict');
        } catch (QueryException $exception) {
            return $this->queryFailure($socket, $exception);
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    private function receiptWorker($socket, Connection $db, int $actor, int $po, string $token, int $item, string $quantity): int
    {
        $this->workerReady($socket, $db);
        try {
            $restock = app(ReceivePurchaseOrder::class)->execute(
                User::query()->findOrFail($actor), PurchaseOrder::query()->findOrFail($po),
                $token, '27D receipt', null,
                [['purchase_order_item_id' => $item, 'accepted_quantity' => $quantity, 'actual_unit_cost' => '12.50']],
            );
            $this->writeResult($socket, 'received', ['restock_id' => (int) $restock->id]);
        } catch (ValidationException $exception) {
            $this->assertContains($exception->errors(), [
                ['purchase_order' => ['Only a pending or partially received Purchase Order may be received.']],
                ['items.0.accepted_quantity' => ['The accepted quantity exceeds the current outstanding quantity.']],
            ]);
            $this->writeResult($socket, 'receipt-conflict');
        } catch (QueryException $exception) {
            return $this->queryFailure($socket, $exception);
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    private function editWorker($socket, Connection $db, int $actor, int $po, string $revision, int $variant): int
    {
        $this->workerReady($socket, $db);
        try {
            app(UpdatePurchaseOrder::class)->execute(
                User::query()->findOrFail($actor), PurchaseOrder::query()->findOrFail($po),
                $revision, 'Changed supplier', 'Changed note', [$this->orderLine($variant, '6.000', '13.00')],
            );
            $this->writeResult($socket, 'edited');
        } catch (ValidationException $exception) {
            $this->assertSame(['purchase_order' => ['Only a pending Purchase Order may be edited.']], $exception->errors());
            $this->writeResult($socket, 'edit-conflict');
        } catch (QueryException $exception) {
            return $this->queryFailure($socket, $exception);
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    private function queryFailure($socket, QueryException $exception): int
    {
        $this->writeResult($socket, 'query-failure', ['driver_code' => (int) ($exception->errorInfo[1] ?? 0)]);

        return self::CHILD_EXIT_UNEXPECTED;
    }

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

    private function hold(Connection $gate, string $table, int $id): void
    {
        $gate->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $gate->beginTransaction();
        $this->assertNotNull($gate->table($table)->where('id', $id)->lockForUpdate()->first());
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
                $this->fail('27D database failure, driver code '.($result['data']['driver_code'] ?? 0));
            }
            if ($result['status'] === 'exception') {
                $this->fail('27D worker exception: '.($result['data']['class'] ?? 'unknown'));
            }
            $this->waitForSuccessfulChild($worker['pid']);
            $worker['joined'] = true;
            $results[] = $result;
        }
        unset($worker);

        return $results;
    }

    private function sortedStatuses(array $results): array
    {
        $statuses = array_column($results, 'status');
        sort($statuses);

        return $statuses;
    }

    private function purchaseOrderStatus(Connection $db, int $po): string
    {
        return (string) $db->table('purchase_orders')->where('id', $po)->value('status');
    }

    private function stock(Connection $db, int $variant): string
    {
        return (string) $db->table('product_variants')->where('id', $variant)->value('current_stock');
    }

    private function assertCoverage(Connection $db, int $item, string $ordered, string $accepted, string $transferred): void
    {
        $this->assertSame($ordered, (string) $db->table('purchase_order_items')->where('id', $item)->value('ordered_quantity'));
        $acceptedRows = $db->table('restock_items')->where('purchase_order_item_id', $item)->pluck('quantity');
        $actualAccepted = '0.000';
        foreach ($acceptedRows as $quantity) {
            $actualAccepted = bcadd($actualAccepted, (string) $quantity, 3);
        }
        $this->assertSame($accepted, $actualAccepted);
        $actualTransferred = $db->table('purchase_order_item_transfers')->where('source_purchase_order_item_id', $item)->value('quantity');
        $this->assertSame($transferred, $actualTransferred === null ? '0.000' : (string) $actualTransferred);
        $outstanding = bcsub(bcsub($ordered, $actualAccepted, 3), $transferred, 3);
        $this->assertGreaterThanOrEqual(0, bccomp($outstanding, '0.000', 3));
    }

    private function assertTransferSet(Connection $db, int $source, int $child, array $expected, int $actor): void
    {
        $purchaseOrder = $db->table('purchase_orders')->where('id', $child)->sole();
        $this->assertSame($source, (int) $purchaseOrder->parent_purchase_order_id);
        $this->assertSame($actor, (int) $purchaseOrder->created_by);
        $this->assertSame('pending', $purchaseOrder->status);
        $this->assertSame('27D follow-up supplier', $purchaseOrder->supplier_name);
        $this->assertSame('27D planning note', $purchaseOrder->notes);
        $this->assertSame(1, $db->table('purchase_orders')->where('parent_purchase_order_id', $source)->count());
        $items = $db->table('purchase_order_items')->where('purchase_order_id', $child)->get();
        $this->assertCount(count($expected), $items);
        $transfers = $db->table('purchase_order_item_transfers')->whereIn('source_purchase_order_item_id', array_keys($expected))->get();
        $this->assertCount(count($expected), $transfers);
        foreach ($expected as $sourceItem => [$variant, $quantity, $cost]) {
            $transfer = $transfers->firstWhere('source_purchase_order_item_id', $sourceItem);
            $this->assertNotNull($transfer);
            $this->assertSame($actor, (int) $transfer->created_by);
            $this->assertSame($quantity, (string) $transfer->quantity);
            $this->assertSame(1, $db->table('purchase_order_item_transfers')->where('target_purchase_order_item_id', $transfer->target_purchase_order_item_id)->count());
            $target = $items->firstWhere('id', $transfer->target_purchase_order_item_id);
            $this->assertNotNull($target);
            $this->assertSame($variant, (int) $target->product_variant_id);
            $this->assertSame($variant, (int) $db->table('purchase_order_items')->where('id', $sourceItem)->value('product_variant_id'));
            $this->assertSame($quantity, (string) $target->ordered_quantity);
            $this->assertSame($cost, (string) $target->expected_unit_cost);
        }
    }

    private function assertReceiptEvidence(Connection $db, int $po, int $item, int $variant, string $token, bool $received): void
    {
        if (! $received) {
            $this->assertSame(0, $db->table('restocks')->where('submission_token', $token)->count());
            $this->assertSame(0, $db->table('restock_items')->where('purchase_order_item_id', $item)->count());

            return;
        }
        $restock = $db->table('restocks')->where('submission_token', $token)->sole();
        $this->assertSame($po, (int) $restock->purchase_order_id);
        $line = $db->table('restock_items')->where('restock_id', $restock->id)->sole();
        $this->assertSame($item, (int) $line->purchase_order_item_id);
        $this->assertSame($variant, (int) $line->product_variant_id);
        $this->assertSame('2.125', (string) $line->quantity);
        $this->assertSame('12.50', (string) $line->unit_cost);
        $movement = $db->table('stock_movements')->where('restock_item_id', $line->id)->sole();
        $this->assertSame('RESTOCK', $movement->movement_type);
        $this->assertSame('0.000', (string) $movement->quantity_before);
        $this->assertSame('2.125', (string) $movement->quantity_change);
        $this->assertSame('2.125', (string) $movement->quantity_after);
    }

    private function evidence(Connection $db, array $f): array
    {
        return [
            'orders' => $db->table('purchase_orders')->whereIn('created_by', $f['users'])->count(),
            'items' => $db->table('purchase_order_items')->whereIn('product_variant_id', $f['variants'])->count(),
            'transfers' => $db->table('purchase_order_item_transfers')->whereIn('created_by', $f['users'])->count(),
            'restocks' => $db->table('restocks')->whereIn('recorded_by', $f['users'])->count(),
            'restock_items' => $db->table('restock_items')->whereIn('product_variant_id', $f['variants'])->count(),
            'movements' => $db->table('stock_movements')->whereIn('product_variant_id', $f['variants'])->count(),
            'audit' => $db->table('audit_logs')->whereIn('user_id', $f['users'])->count(),
            'variants' => $db->table('product_variants')->whereIn('id', $f['variants'])->orderBy('id')->get(['id', 'current_stock', 'cost_price'])->map(fn (object $row): array => [(int) $row->id, (string) $row->current_stock, (string) $row->cost_price])->all(),
        ];
    }

    private function assertEvidenceDelta(Connection $db, array $f, array $before, int $orders, int $transfers, int $receipts): void
    {
        $after = $this->evidence($db, $f);
        $this->assertSame($before['orders'] + $orders, $after['orders']);
        $this->assertSame($before['items'] + $transfers, $after['items']);
        $this->assertSame($before['transfers'] + $transfers, $after['transfers']);
        $this->assertSame($before['restocks'] + $receipts, $after['restocks']);
        $this->assertSame($before['restock_items'] + $receipts, $after['restock_items']);
        $this->assertSame($before['movements'] + $receipts, $after['movements']);
        $this->assertSame($before['audit'], $after['audit']);
    }

    private function assertUnchangedInventory(Connection $db, array $f, array $before): void
    {
        $this->assertSame($before['variants'], $this->evidence($db, $f)['variants']);
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
            $orders = $db->table('purchase_orders')->whereIn('created_by', $f['users'])->pluck('id')->all();
            $items = $db->table('purchase_order_items')->whereIn('purchase_order_id', $orders)->pluck('id')->all();
            $restocks = $db->table('restocks')->whereIn('recorded_by', $f['users'])->pluck('id')->all();
            $db->table('stock_movements')->whereIn('product_variant_id', $f['variants'])->delete();
            $db->table('restock_items')->whereIn('restock_id', $restocks)->delete();
            $db->table('restocks')->whereIn('id', $restocks)->delete();
            $db->table('purchase_order_item_transfers')->whereIn('source_purchase_order_item_id', $items)->delete();
            $db->table('purchase_order_items')->whereIn('id', $items)->delete();
            $db->table('purchase_orders')->whereIn('id', $orders)->whereNotNull('parent_purchase_order_id')->delete();
            $db->table('purchase_orders')->whereIn('id', $orders)->delete();
            $db->table('audit_logs')->whereIn('user_id', $f['users'])->delete();
            $db->table('product_variants')->whereIn('id', $f['variants'])->delete();
            $db->table('products')->where('id', $f['product'])->delete();
            $db->table('categories')->where('id', $f['category'])->delete();
            $db->table('users')->whereIn('id', $f['users'])->delete();
        });
    }
}

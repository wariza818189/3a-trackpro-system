<?php

namespace Tests\MySql;

use App\Models\Concerns\ImmutableRecord;
use App\Models\PurchaseOrder;
use App\Models\RestockDamageItem;
use App\Models\User;
use App\Services\Procurement\CreateFollowUpPurchaseOrder;
use App\Services\Procurement\CreatePurchaseOrder;
use App\Services\Procurement\ReceivePurchaseOrder;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Throwable;

final class PurchaseOrder30dConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const TABLES = [
        'audit_logs', 'cash_register_sessions', 'categories', 'migrations', 'product_variants',
        'products', 'purchase_order_item_transfers', 'purchase_order_items', 'purchase_orders',
        'restock_damage_items', 'restock_items', 'restocks', 'sale_items', 'sales', 'stock_movements', 'users',
    ];

    private const FIXTURE_TABLES = [
        'users', 'categories', 'products', 'product_variants', 'stock_movements',
        'purchase_orders', 'purchase_order_items', 'purchase_order_item_transfers',
        'restocks', 'restock_items', 'restock_damage_items', 'audit_logs',
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
        // The shared harness inventory predates the procurement and damage migrations.
        $db = $this->guardedMySql24eConnection();
        if ($db->getName() !== self::CONNECTION || $db->scalar('SELECT DATABASE()') !== self::DATABASE
            || (int) $db->scalar('SELECT @@autocommit') !== 1
            || strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))) !== 'REPEATABLE-READ'
            || ! preg_match('/\A8\.\d+/', (string) $db->scalar('SELECT VERSION()'))) {
            throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: dedicated MySQL session');
        }
        $tables = array_map(static fn (object $row): string => $row->name, $db->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [self::DATABASE, 'BASE TABLE'],
        ));
        if ($tables !== self::TABLES || $db->table('migrations')->orderBy('migration')->pluck('migration')->all() !== self::MIGRATIONS) {
            throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: exact table inventory or migration ledger');
        }
        $engines = $db->select('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [self::DATABASE, 'BASE TABLE']);
        if (count($engines) !== count(self::TABLES) || array_filter($engines, static fn (object $row): bool => $row->engine !== 'InnoDB') !== []) {
            throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: InnoDB');
        }
        $columns = $db->select('SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [self::DATABASE, 'restock_damage_items']);
        $byName = [];
        foreach ($columns as $column) {
            $byName[$column->name] = [$column->type, $column->nullable];
        }
        foreach (['restock_id', 'purchase_order_item_id', 'product_variant_id'] as $name) {
            if (($byName[$name] ?? null) !== ['bigint unsigned', 'NO']) {
                throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: damage foreign key column');
            }
        }
        if (($byName['damaged_quantity'] ?? null) !== ['decimal(14,3)', 'NO']
            || ($byName['damage_note'] ?? null) !== ['text', 'NO']
            || ! isset($byName['created_at']) || isset($byName['updated_at'])) {
            throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: damage value columns');
        }
        foreach (['product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot'] as $name) {
            if (! isset($byName[$name]) || $byName[$name][1] !== 'NO') {
                throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: damage snapshot');
            }
        }
        foreach ([
            'restock_id' => 'restocks', 'purchase_order_item_id' => 'purchase_order_items', 'product_variant_id' => 'product_variants',
        ] as $column => $target) {
            $foreign = $db->selectOne('SELECT k.REFERENCED_TABLE_NAME AS target, k.REFERENCED_COLUMN_NAME AS target_column, r.DELETE_RULE AS deletion, r.UPDATE_RULE AS modification FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA = ? AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?', [self::DATABASE, 'restock_damage_items', $column]);
            if ($foreign?->target !== $target || $foreign->target_column !== 'id' || $foreign->deletion !== 'RESTRICT' || $foreign->modification !== 'RESTRICT') {
                throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: damage foreign key');
            }
        }
        $unique = $db->select('SELECT COLUMN_NAME AS name, NON_UNIQUE AS non_unique FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX', [self::DATABASE, 'restock_damage_items', 'restock_damage_items_restock_id_purchase_order_item_id_unique']);
        if (array_map(static fn (object $row): array => [$row->name, (int) $row->non_unique], $unique) !== [['restock_id', 0], ['purchase_order_item_id', 0]]) {
            throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: damage pair uniqueness');
        }
        $checks = array_map(static fn (object $row): string => $row->name, $db->select('SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ?', [self::DATABASE, 'restock_damage_items', 'CHECK']));
        sort($checks);
        if ($checks !== ['restock_damage_items_note_nonblank', 'restock_damage_items_quantity_positive']) {
            throw new MySql24eGuardException('BLOCKED_30D_SCHEMA_PRECONDITION: damage checks');
        }

        return $db;
    }

    public function test_guarded_readiness_and_damage_schema(): void
    {
        $db = $this->readyConnection();
        $this->assertSame(self::CONNECTION, $db->getName());
        $this->assertSame(self::DATABASE, $db->scalar('SELECT DATABASE()'));
        $this->assertSame('testing', app()->environment());
        $this->assertSame(1, (int) $db->scalar('SELECT @@autocommit'));
        $this->assertSame('REPEATABLE-READ', strtoupper(str_replace('_', '-', (string) $db->scalar('SELECT @@transaction_isolation'))));
        $this->assertSame(10, (int) $db->scalar('SELECT @@innodb_lock_wait_timeout'));
    }

    public function test_accepted_receipt_and_damage_receipt_on_same_line_both_commit(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            [$po, $item] = $this->order($db, $f);
            $acceptedToken = (string) Str::uuid();
            $damageToken = (string) Str::uuid();
            $before = $this->evidence($db, $f);
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiptWorker($socket, $child, $f['users'][1], (int) $po->id, $acceptedToken, $item, true));
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiptWorker($socket, $child, $f['users'][2], (int) $po->id, $damageToken, $item, false));
            $this->race($workers, $gate, $f['variant']);
            [$accepted, $damage] = $this->results($workers);
            $this->assertSame(['received', 'received'], [$accepted['status'], $damage['status']]);
            $this->assertNotSame($accepted['data']['restock_id'], $damage['data']['restock_id']);
            $this->assertAccepted($db, $f, $po, $item, $acceptedToken, (int) $accepted['data']['restock_id']);
            $this->assertDamage($db, $f, $po, $item, $damageToken, (int) $damage['data']['restock_id'], '7.000');
            $this->assertCoverage($db, $item, '2.000', '0.000', '3.000');
            $this->assertSame('partially_received', $this->purchaseOrderStatus($db, $po));
            $this->assertSame('2.000', $this->stock($db, $f));
            $this->assertSame('12.50', $this->cost($db, $f));
            $this->assertDelta($db, $f, $before, 0, 0, 2, 1, 1, 1);
        });
    }

    public function test_concurrent_equivalent_damage_only_token_replay_returns_one_restock(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            [$po, $item] = $this->order($db, $f);
            $token = (string) Str::uuid();
            $before = $this->evidence($db, $f);
            foreach ([0, 1] as $ignored) {
                $this->start($workers, fn ($socket, Connection $child): int => $this->receiptWorker($socket, $child, $f['users'][1], (int) $po->id, $token, $item, false));
            }
            // Both calls contend at the same actor lock. This proves replay identity,
            // but does not prove the unique-index collision recovery branch.
            $this->race($workers, $gate, $f['users'][1], 'users');
            [$first, $second] = $this->results($workers);
            $this->assertSame(['received', 'received'], [$first['status'], $second['status']]);
            $this->assertSame($first['data']['restock_id'], $second['data']['restock_id']);
            $this->assertDamage($db, $f, $po, $item, $token, (int) $first['data']['restock_id'], '7.000');
            $this->assertCoverage($db, $item, '0.000', '0.000', '5.000');
            $this->assertSame('pending', $this->purchaseOrderStatus($db, $po));
            $this->assertSame('0.000', $this->stock($db, $f));
            $this->assertSame('10.00', $this->cost($db, $f));
            $this->assertDelta($db, $f, $before, 0, 0, 1, 0, 1, 0);
        });
    }

    public function test_damage_receipt_and_follow_up_transfer_serialize_same_source_line(): void
    {
        $this->scenario(function (Connection $db, array $f, array &$workers, ?Connection &$gate): void {
            [$po, $item] = $this->order($db, $f);
            $damageToken = (string) Str::uuid();
            $followToken = (string) Str::uuid();
            $before = $this->evidence($db, $f);
            $this->start($workers, fn ($socket, Connection $child): int => $this->receiptWorker($socket, $child, $f['users'][2], (int) $po->id, $damageToken, $item, false));
            $this->start($workers, fn ($socket, Connection $child): int => $this->followWorker($socket, $child, $f['users'][0], (int) $po->id, $followToken, $item));
            $this->race($workers, $gate, $f['variant']);
            [$damage, $follow] = $this->results($workers);
            $this->assertSame('followed', $follow['status']);
            $this->assertContains($damage['status'], ['received', 'receipt-conflict']);
            $received = $damage['status'] === 'received';
            if ($received) {
                $this->assertDamage($db, $f, $po, $item, $damageToken, (int) $damage['data']['restock_id'], '7.000');
            } else {
                $this->assertSame(0, $db->table('restocks')->where('submission_token', $damageToken)->count());
                $this->assertSame(0, $db->table('restock_damage_items')->where('purchase_order_item_id', $item)->count());
            }
            $child = $db->table('purchase_orders')->where('id', $follow['data']['child_id'])->sole();
            $this->assertSame((int) $po->id, (int) $child->parent_purchase_order_id);
            $this->assertSame($followToken, $child->submission_token);
            $this->assertSame($f['users'][0], (int) $child->created_by);
            $this->assertSame('pending', $child->status);
            $this->assertSame(1, $db->table('purchase_orders')->where('parent_purchase_order_id', $po->id)->count());
            $target = $db->table('purchase_order_items')->where('purchase_order_id', $child->id)->sole();
            $this->assertSame($f['variant'], (int) $target->product_variant_id);
            $this->assertSame('5.000', (string) $target->ordered_quantity);
            $this->assertSame('11.25', (string) $target->expected_unit_cost);
            $transfer = $db->table('purchase_order_item_transfers')->where('source_purchase_order_item_id', $item)->sole();
            $this->assertSame((int) $target->id, (int) $transfer->target_purchase_order_item_id);
            $this->assertSame($f['users'][0], (int) $transfer->created_by);
            $this->assertSame('5.000', (string) $transfer->quantity);
            $this->assertCoverage($db, $item, '0.000', '5.000', '0.000');
            $this->assertSame('closed_with_remainder', $this->purchaseOrderStatus($db, $po));
            $this->assertSame('0.000', $this->stock($db, $f));
            $this->assertSame('10.00', $this->cost($db, $f));
            $this->assertDelta($db, $f, $before, 1, 1, $received ? 1 : 0, 0, $received ? 1 : 0, 0);
        });
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
                    $this->assertSame($baseline, $this->counts($this->readyConnection()), 'Fixture cleanup did not restore all table counts.');
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
        $marker = 'po30d_'.bin2hex(random_bytes(6));

        return $db->transaction(function () use ($db, $marker): array {
            $users = [];
            foreach (['admin', 'staff', 'staff'] as $i => $role) {
                $users[] = (int) $db->table('users')->insertGetId([
                    'name' => $marker.'_user_'.$i, 'username' => $marker.'_user_'.$i,
                    'password' => 'not-used-for-login', 'role' => $role, 'status' => 'active',
                ]);
            }
            $category = (int) $db->table('categories')->insertGetId(['name' => $marker.'_category', 'status' => 'active']);
            $product = (int) $db->table('products')->insertGetId(['category_id' => $category, 'name' => $marker.'_product', 'status' => 'active']);
            $variant = (int) $db->table('product_variants')->insertGetId([
                'product_id' => $product, 'size' => '30D', 'type_series' => '', 'thickness' => '',
                'unit' => 'kg', 'quantity_mode' => 'fractional', 'cost_price' => '10.00',
                'selling_price' => '20.00', 'current_stock' => '0.000',
                'low_stock_threshold' => '1.000', 'status' => 'active',
            ]);
            $db->table('stock_movements')->insert([
                'product_variant_id' => $variant, 'movement_type' => 'INITIAL_STOCK',
                'quantity_before' => '0.000', 'quantity_change' => '0.000', 'quantity_after' => '0.000',
                'performed_by' => $users[0], 'reason' => 'Synthetic 30D opening inventory',
            ]);

            return compact('users', 'category', 'product', 'variant') + ['order_token' => (string) Str::uuid()];
        });
    }

    private function order(Connection $db, array $f): array
    {
        $po = app(CreatePurchaseOrder::class)->execute(
            User::query()->findOrFail($f['users'][0]), $f['order_token'], '30D supplier', null,
            [['product_variant_id' => $f['variant'], 'ordered_quantity' => '5.000', 'expected_unit_cost' => '10.00']],
        );
        $item = (int) $db->table('purchase_order_items')->where('purchase_order_id', $po->id)->sole()->id;

        return [$po, $item];
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

    private function receiptWorker($socket, Connection $db, int $actor, int $po, string $token, int $item, bool $accepted): int
    {
        $this->workerReady($socket, $db);
        try {
            $line = $accepted
                ? ['purchase_order_item_id' => $item, 'accepted_quantity' => '2.000', 'actual_unit_cost' => '12.50']
                : ['purchase_order_item_id' => $item, 'damaged_quantity' => '7.000', 'damage_note' => '  Crushed on arrival  '];
            $restock = app(ReceivePurchaseOrder::class)->execute(
                User::query()->findOrFail($actor), PurchaseOrder::query()->findOrFail($po),
                $token, '30D receipt', null, [$line],
            );
            $this->writeResult($socket, 'received', ['restock_id' => (int) $restock->id]);
        } catch (ValidationException $exception) {
            $this->assertFalse($accepted, 'The accepted receipt must remain valid in both serializations.');
            $this->assertSame(['purchase_order' => ['Only a pending or partially received Purchase Order may be received.']], $exception->errors());
            $this->writeResult($socket, 'receipt-conflict');
        } catch (QueryException $exception) {
            return $this->queryFailure($socket, $exception);
        }

        return self::CHILD_EXIT_SUCCESS;
    }

    private function followWorker($socket, Connection $db, int $actor, int $po, string $token, int $item): int
    {
        $this->workerReady($socket, $db);
        try {
            $child = app(CreateFollowUpPurchaseOrder::class)->execute(
                User::query()->findOrFail($actor), PurchaseOrder::query()->findOrFail($po),
                $token, '30D follow-up supplier', '30D planning note',
                [['source_purchase_order_item_id' => $item, 'expected_unit_cost' => '11.25']],
            );
            $this->writeResult($socket, 'followed', ['child_id' => (int) $child->id]);
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

    private function race(array $workers, ?Connection &$gate, int $id, string $table = 'product_variants'): void
    {
        $gate = $this->reconnectParentAfterFork();
        foreach ($workers as $worker) {
            $this->assertSame('ready', $this->readStatus($worker['socket']));
        }
        $gate->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $gate->beginTransaction();
        $this->assertNotNull($gate->table($table)->where('id', $id)->lockForUpdate()->first());
        foreach ($workers as $worker) {
            $this->writeStatus($worker['socket'], 'go');
        }
        foreach ($workers as $worker) {
            $this->assertSame('attempting', $this->readStatus($worker['socket']));
        }
        foreach ($workers as $worker) {
            $this->assertNoWorkerResult($worker['socket'], 'A worker passed the contention gate.');
        }
        $gate->commit();
    }

    private function results(array &$workers): array
    {
        $results = [];
        foreach ($workers as &$worker) {
            $result = $this->readResult($worker['socket']);
            if ($result['status'] === 'query-failure') {
                $this->fail('30D database failure, driver code '.($result['data']['driver_code'] ?? 0));
            }
            if ($result['status'] === 'exception') {
                $this->fail('30D worker exception: '.($result['data']['class'] ?? 'unknown'));
            }
            $this->waitForSuccessfulChild($worker['pid']);
            $worker['joined'] = true;
            $results[] = $result;
        }
        unset($worker);

        return $results;
    }

    private function assertDamage(Connection $db, array $f, PurchaseOrder $po, int $item, string $token, int $restockId, string $quantity): void
    {
        $restock = $db->table('restocks')->where('submission_token', $token)->sole();
        $this->assertSame($restockId, (int) $restock->id);
        $this->assertSame((int) $po->id, (int) $restock->purchase_order_id);
        $this->assertContains((int) $restock->recorded_by, [$f['users'][1], $f['users'][2]]);
        $this->assertSame('0.00', (string) $restock->total_cost);
        $this->assertSame(0, $db->table('restock_items')->where('restock_id', $restockId)->count());
        $damage = $db->table('restock_damage_items')->where('restock_id', $restockId)->sole();
        $this->assertSame(1, $db->table('restock_damage_items')->where('restock_id', $restockId)->where('purchase_order_item_id', $item)->count());
        $this->assertSame($item, (int) $damage->purchase_order_item_id);
        $this->assertSame($f['variant'], (int) $damage->product_variant_id);
        $this->assertSame($quantity, (string) $damage->damaged_quantity);
        $this->assertSame('Crushed on arrival', $damage->damage_note);
        $source = $db->table('purchase_order_items')->where('id', $item)->sole();
        foreach (['product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot'] as $field) {
            $this->assertSame($source->$field, $damage->$field);
        }
        $this->assertTrue(in_array(ImmutableRecord::class, class_uses_recursive(RestockDamageItem::class), true));
    }

    private function assertAccepted(Connection $db, array $f, PurchaseOrder $po, int $item, string $token, int $restockId): void
    {
        $restock = $db->table('restocks')->where('submission_token', $token)->sole();
        $this->assertSame($restockId, (int) $restock->id);
        $this->assertSame((int) $po->id, (int) $restock->purchase_order_id);
        $this->assertSame($f['users'][1], (int) $restock->recorded_by);
        $this->assertSame('25.00', (string) $restock->total_cost);
        $this->assertSame(0, $db->table('restock_damage_items')->where('restock_id', $restockId)->count());
        $line = $db->table('restock_items')->where('restock_id', $restockId)->sole();
        $this->assertSame($item, (int) $line->purchase_order_item_id);
        $this->assertSame($f['variant'], (int) $line->product_variant_id);
        $this->assertSame('2.000', (string) $line->quantity);
        $this->assertSame('12.50', (string) $line->unit_cost);
        $movement = $db->table('stock_movements')->where('restock_item_id', $line->id)->sole();
        $this->assertSame('RESTOCK', $movement->movement_type);
        $this->assertSame('0.000', (string) $movement->quantity_before);
        $this->assertSame('2.000', (string) $movement->quantity_change);
        $this->assertSame('2.000', (string) $movement->quantity_after);
    }

    private function assertCoverage(Connection $db, int $item, string $accepted, string $transferred, string $outstanding): void
    {
        $source = $db->table('purchase_order_items')->where('id', $item)->sole();
        $this->assertSame('5.000', (string) $source->ordered_quantity);
        $acceptedSum = '0.000';
        foreach ($db->table('restock_items')->where('purchase_order_item_id', $item)->pluck('quantity') as $quantity) {
            $acceptedSum = bcadd($acceptedSum, (string) $quantity, 3);
        }
        $transferredSum = '0.000';
        foreach ($db->table('purchase_order_item_transfers')->where('source_purchase_order_item_id', $item)->pluck('quantity') as $quantity) {
            $transferredSum = bcadd($transferredSum, (string) $quantity, 3);
        }
        $this->assertSame($accepted, $acceptedSum);
        $this->assertSame($transferred, $transferredSum);
        $this->assertLessThanOrEqual(0, bccomp(bcadd($acceptedSum, $transferredSum, 3), '5.000', 3));
        $this->assertSame($outstanding, bcsub(bcsub('5.000', $acceptedSum, 3), $transferredSum, 3));
    }

    private function evidence(Connection $db, array $f): array
    {
        return [
            $db->table('purchase_orders')->whereIn('created_by', $f['users'])->count(),
            $db->table('purchase_order_items')->where('product_variant_id', $f['variant'])->count(),
            $db->table('purchase_order_item_transfers')->whereIn('created_by', $f['users'])->count(),
            $db->table('restocks')->whereIn('recorded_by', $f['users'])->count(),
            $db->table('restock_items')->where('product_variant_id', $f['variant'])->count(),
            $db->table('restock_damage_items')->where('product_variant_id', $f['variant'])->count(),
            $db->table('stock_movements')->where('product_variant_id', $f['variant'])->count(),
            $db->table('audit_logs')->whereIn('user_id', $f['users'])->count(),
        ];
    }

    private function assertDelta(Connection $db, array $f, array $before, int $children, int $transfers, int $restocks, int $accepted, int $damage, int $movements): void
    {
        $after = $this->evidence($db, $f);
        $this->assertSame([
            $before[0] + $children, $before[1] + $transfers, $before[2] + $transfers,
            $before[3] + $restocks, $before[4] + $accepted, $before[5] + $damage,
            $before[6] + $movements, $before[7],
        ], $after);
    }

    private function purchaseOrderStatus(Connection $db, PurchaseOrder $po): string
    {
        return (string) $db->table('purchase_orders')->where('id', $po->id)->value('status');
    }

    private function stock(Connection $db, array $f): string
    {
        return (string) $db->table('product_variants')->where('id', $f['variant'])->value('current_stock');
    }

    private function cost(Connection $db, array $f): string
    {
        return (string) $db->table('product_variants')->where('id', $f['variant'])->value('cost_price');
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
            $db->table('stock_movements')->where('product_variant_id', $f['variant'])->delete();
            $db->table('restock_damage_items')->whereIn('restock_id', $restocks)->delete();
            $db->table('restock_items')->whereIn('restock_id', $restocks)->delete();
            $db->table('restocks')->whereIn('id', $restocks)->delete();
            $db->table('purchase_order_item_transfers')->whereIn('source_purchase_order_item_id', $items)->delete();
            $db->table('purchase_order_items')->whereIn('id', $items)->delete();
            $db->table('purchase_orders')->whereIn('id', $orders)->whereNotNull('parent_purchase_order_id')->delete();
            $db->table('purchase_orders')->whereIn('id', $orders)->delete();
            $db->table('audit_logs')->whereIn('user_id', $f['users'])->delete();
            $db->table('product_variants')->where('id', $f['variant'])->delete();
            $db->table('products')->where('id', $f['product'])->delete();
            $db->table('categories')->where('id', $f['category'])->delete();
            $db->table('users')->whereIn('id', $f['users'])->delete();
        });
    }
}

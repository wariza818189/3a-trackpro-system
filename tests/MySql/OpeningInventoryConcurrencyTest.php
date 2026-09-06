<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordOpeningInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class OpeningInventoryConcurrencyTest extends MySqlTestCase
{
    public function test_zero_opening_inventory_is_serialized_and_recorded_exactly_once(): void
    {
        foreach (['pcntl_fork', 'pcntl_waitpid', 'stream_socket_pair'] as $function) {
            $this->assertTrue(function_exists($function), "{$function} is required for the MySQL concurrency proof.");
        }

        $connection = DB::connection('mysql_testing');
        $connection->rollBack();

        [$actorId, $categoryId, $productId, $variantId] = $this->createCommittedFixture();
        DB::purge('mysql_testing');

        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);

            throw new RuntimeException('Unable to create the concurrency-test IPC channel.');
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);

            throw new RuntimeException('Unable to fork the second opening-inventory process.');
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $this->runCompetingProcess($sockets[1], $variantId, $actorId);
        }

        fclose($sockets[1]);
        $socket = $sockets[0];
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));

            $connection = $this->guardMySqlTestConnection();
            $variant = ProductVariant::query()->with('product:id,category_id')->findOrFail($variantId);
            $actor = User::query()->findOrFail($actorId);
            $connection->beginTransaction();

            app(RecordOpeningInventory::class)->execute(
                $variant,
                $actor,
                '0.000',
                'Initial physical count by request A',
            );

            fwrite($socket, "go\n");
            fflush($socket);

            $snapshot = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('snapshot-established', $snapshot['status'] ?? null);
            $this->assertSame('REPEATABLE-READ', $snapshot['isolation'] ?? null);
            $this->assertSame('0.000', $snapshot['current_stock'] ?? null);
            $this->assertSame(0, $snapshot['initial_stock_count'] ?? null);
            $this->assertSame('attempting', $this->readLine($socket, 10));

            $read = [$socket];
            $write = null;
            $except = null;
            $completedWhileLocked = stream_select($read, $write, $except, 0, 500000);
            $this->assertSame(0, $completedWhileLocked, 'Request B completed while request A still held the hierarchy locks.');

            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('rejected', $result['status'] ?? null);
            $this->assertSame(
                'Opening inventory has already been recorded for this variant.',
                $result['message'] ?? null,
            );
            $this->assertSame('0.000', $result['stale_snapshot_stock'] ?? null);
            $this->assertSame(0, $result['stale_snapshot_initial_stock_count'] ?? null);

            $waitedPid = pcntl_waitpid($pid, $status);
            $childWaited = true;
            $this->assertSame($pid, $waitedPid);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('0.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $this->assertSame(1, StockMovement::query()->where([
                'product_variant_id' => $variantId,
                'movement_type' => StockMovement::TYPE_INITIAL_STOCK,
            ])->count());
            $this->assertSame(1, StockMovement::query()->where('product_variant_id', $variantId)->count());
            $this->assertSame(
                'Initial physical count by request A',
                StockMovement::query()->where('product_variant_id', $variantId)->value('reason'),
            );
        } finally {
            if (isset($connection) && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            fclose($socket);

            if (! $childWaited) {
                pcntl_waitpid($pid, $status);
            }

            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);
        }
    }

    /** @return array{int, int, int, int} */
    private function createCommittedFixture(): array
    {
        $this->guardMySqlTestConnection();

        return DB::connection('mysql_testing')->transaction(function (): array {
            $actor = new User;
            $actor->name = 'Opening Inventory Concurrency Admin';
            $actor->username = 'opening_inventory_concurrency_'.bin2hex(random_bytes(8));
            $actor->password = 'not-used-for-login';
            $actor->role = User::ROLE_ADMIN;
            $actor->status = User::STATUS_ACTIVE;
            $actor->save();

            $category = new Category(['name' => 'Concurrency '.bin2hex(random_bytes(6))]);
            $category->status = Category::STATUS_ACTIVE;
            $category->save();

            $product = new Product(['name' => 'Zero-opening race']);
            $product->category_id = $category->id;
            $product->status = Product::STATUS_ACTIVE;
            $product->save();

            $variant = new ProductVariant([
                'size' => 'Concurrent zero',
                'type_series' => '',
                'thickness' => '',
                'unit' => 'piece',
                'quantity_mode' => 'whole',
                'cost_price' => null,
                'selling_price' => '1.00',
                'low_stock_threshold' => '0.000',
            ]);
            $variant->product_id = $product->id;
            $variant->status = ProductVariant::STATUS_ACTIVE;
            $variant->save();

            return [$actor->id, $category->id, $product->id, $variant->id];
        });
    }

    /** @param resource $socket */
    private function runCompetingProcess($socket, int $variantId, int $actorId): never
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function () use ($socket): never {
            fwrite($socket, json_encode(['status' => 'timeout'], JSON_THROW_ON_ERROR)."\n");
            exit(2);
        });
        pcntl_alarm(20);

        try {
            DB::purge('mysql_testing');
            $connection = $this->guardMySqlTestConnection();
            fwrite($socket, "ready\n");
            fflush($socket);

            if ($this->readLine($socket, 10) !== 'go') {
                throw new RuntimeException('Request B did not receive the start signal.');
            }

            $variant = ProductVariant::query()->with('product:id,category_id')->findOrFail($variantId);
            $actor = User::query()->findOrFail($actorId);
            $isolation = (string) $connection->scalar('SELECT @@transaction_isolation');
            $connection->beginTransaction();

            // B intentionally establishes a REPEATABLE READ snapshot before A commits.
            // Its ordinary reads remain stale; the service's post-lock current read must
            // still observe A's committed INITIAL_STOCK after the lock wait ends.
            $snapshotStock = (string) $connection->table('product_variants')
                ->where('id', $variantId)
                ->value('current_stock');
            $snapshotInitialCount = $connection->table('stock_movements')
                ->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
                ->count();

            fwrite($socket, json_encode([
                'status' => 'snapshot-established',
                'isolation' => $isolation,
                'current_stock' => $snapshotStock,
                'initial_stock_count' => $snapshotInitialCount,
            ], JSON_THROW_ON_ERROR)."\n");
            fwrite($socket, "attempting\n");
            fflush($socket);

            try {
                app(RecordOpeningInventory::class)->execute(
                    $variant,
                    $actor,
                    '0.000',
                    'Initial physical count by request B',
                );
                $result = ['status' => 'unexpected-success'];
            } catch (ValidationException $exception) {
                $result = [
                    'status' => 'rejected',
                    'message' => $exception->errors()['opening_quantity'][0] ?? null,
                    'stale_snapshot_stock' => (string) $connection->table('product_variants')
                        ->where('id', $variantId)
                        ->value('current_stock'),
                    'stale_snapshot_initial_stock_count' => $connection->table('stock_movements')
                        ->where('product_variant_id', $variantId)
                        ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
                        ->count(),
                ];
            }

            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
            fflush($socket);
            pcntl_alarm(0);
            DB::disconnect('mysql_testing');
            fclose($socket);
            exit($result['status'] === 'rejected' ? 0 : 1);
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            fwrite($socket, json_encode([
                'status' => 'exception',
                'class' => $exception::class,
            ], JSON_THROW_ON_ERROR)."\n");
            fflush($socket);
            exit(3);
        }
    }

    /** @param resource $socket */
    private function readLine($socket, int $timeoutSeconds): string
    {
        $read = [$socket];
        $write = null;
        $except = null;
        $ready = stream_select($read, $write, $except, $timeoutSeconds);

        if ($ready !== 1) {
            throw new RuntimeException('Timed out waiting for the concurrency-test process.');
        }

        $line = fgets($socket);
        if ($line === false) {
            throw new RuntimeException('The concurrency-test IPC channel closed unexpectedly.');
        }

        return trim($line);
    }

    private function cleanupFixture(int $actorId, int $categoryId, int $productId, int $variantId): void
    {
        DB::purge('mysql_testing');
        $this->guardMySqlTestConnection();
        DB::table('stock_movements')->where('product_variant_id', $variantId)->delete();
        DB::table('product_variants')->where('id', $variantId)->delete();
        DB::table('products')->where('id', $productId)->delete();
        DB::table('categories')->where('id', $categoryId)->delete();
        DB::table('users')->where('id', $actorId)->delete();
    }
}

<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restock;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordOpeningInventory;
use App\Services\Inventory\RecordRestock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RestockConcurrencyTest extends MySqlTestCase
{
    public function test_concurrent_distinct_restocks_serialize_without_lost_update(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        [$actorId, $categoryId, $productId, $variantId] = $this->createCommittedInitializedFixture();
        $tokenA = Str::uuid()->toString();
        $tokenB = Str::uuid()->toString();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runDistinctRestockChild($child, $actorId, $variantId, $tokenB));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);

            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $actor = User::query()->findOrFail($actorId);
            $connection->beginTransaction();
            app(RecordRestock::class)->execute($actor, $tokenA, 'A', null, [[
                'product_variant_id' => $variantId, 'quantity' => '5', 'unit_cost' => '11',
            ]]);

            fwrite($socket, "go\n");
            fflush($socket);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The competing Restock completed while A held the Variant lock.');
            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('completed', $result['status'] ?? null);
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('22.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $movements = StockMovement::query()
                ->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_RESTOCK)
                ->orderBy('id')
                ->get();
            $this->assertCount(2, $movements);
            $this->assertSame('10.000', $movements[0]->quantity_before);
            $this->assertSame($movements[0]->quantity_after, $movements[1]->quantity_before);
            $this->assertSame('22.000', $movements[1]->quantity_after);
            $this->assertSame(['5.000', '7.000'], $movements->pluck('quantity_change')->sort()->values()->all());
            $this->assertSame(2, DB::table('restock_items')->where('product_variant_id', $variantId)->count());
            $this->assertSame(2, DB::table('restocks')->whereIn('submission_token', [$tokenA, $tokenB])->count());
            $this->assertSame(1, StockMovement::query()->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)->count());
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

    public function test_same_token_race_recovers_winner_with_current_read_despite_stale_snapshot(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        [$actorId, $categoryId, $productId, $variantId] = $this->createCommittedInitializedFixture();
        $token = Str::uuid()->toString();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runSameTokenChild($child, $actorId, $variantId, $token));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);

            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $actor = User::query()->findOrFail($actorId);
            $connection->beginTransaction();
            $winner = app(RecordRestock::class)->execute($actor, $token, 'Same receipt', 'Equivalent retry', [[
                'product_variant_id' => $variantId, 'quantity' => '5', 'unit_cost' => '11',
            ]]);

            fwrite($socket, "go\n");
            fflush($socket);
            $snapshot = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('snapshot-established', $snapshot['status'] ?? null);
            $this->assertSame('REPEATABLE-READ', $snapshot['isolation'] ?? null);
            $this->assertSame(0, $snapshot['token_count'] ?? null);
            $this->assertSame('10.000', $snapshot['stock'] ?? null);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The duplicate token did not wait for A to commit.');
            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('replayed', $result['status'] ?? null);
            $this->assertSame($winner->id, $result['restock_id'] ?? null);
            $this->assertSame(0, $result['stale_token_count'] ?? null);
            $this->assertSame('10.000', $result['stale_stock'] ?? null);
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('15.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $this->assertSame(1, Restock::query()->where('submission_token', $token)->count());
            $this->assertSame(1, DB::table('restock_items')->where('product_variant_id', $variantId)->count());
            $this->assertSame(1, StockMovement::query()->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
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
    private function createCommittedInitializedFixture(): array
    {
        $this->guardMySqlTestConnection();

        return DB::connection('mysql_testing')->transaction(function (): array {
            $actor = new User;
            $actor->name = 'Restock Concurrency Admin';
            $actor->username = 'restock_concurrency_'.bin2hex(random_bytes(8));
            $actor->password = 'not-used-for-login';
            $actor->role = User::ROLE_ADMIN;
            $actor->status = User::STATUS_ACTIVE;
            $actor->save();

            $category = new Category(['name' => 'Restock concurrency '.bin2hex(random_bytes(6))]);
            $category->status = Category::STATUS_ACTIVE;
            $category->save();
            $product = new Product(['name' => 'Concurrent received stock']);
            $product->category_id = $category->id;
            $product->status = Product::STATUS_ACTIVE;
            $product->save();
            $variant = new ProductVariant([
                'size' => 'Concurrent item', 'type_series' => '', 'thickness' => '', 'unit' => 'piece',
                'quantity_mode' => 'whole', 'cost_price' => '10.00', 'selling_price' => '20.00',
                'low_stock_threshold' => '0.000',
            ]);
            $variant->product_id = $product->id;
            $variant->status = ProductVariant::STATUS_ACTIVE;
            $variant->save();

            app(RecordOpeningInventory::class)->execute($variant, $actor, '10', 'Concurrency fixture opening count');

            return [$actor->id, $category->id, $product->id, $variant->id];
        });
    }

    /** @param resource $socket */
    private function runDistinctRestockChild($socket, int $actorId, int $variantId, string $token): never
    {
        $this->configureChildTimeout($socket);
        try {
            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            fwrite($socket, "ready\n");
            fflush($socket);
            if ($this->readLine($socket, 10) !== 'go') {
                throw new RuntimeException('The child did not receive the start signal.');
            }
            fwrite($socket, "attempting\n");
            fflush($socket);
            $restock = app(RecordRestock::class)->execute(
                User::query()->findOrFail($actorId), $token, 'B', null,
                [['product_variant_id' => $variantId, 'quantity' => '7', 'unit_cost' => '12']],
            );
            fwrite($socket, json_encode(['status' => 'completed', 'restock_id' => $restock->id], JSON_THROW_ON_ERROR)."\n");
            $this->finishChild($socket, 0);
        } catch (Throwable $exception) {
            $this->failChild($socket, $exception);
        }
    }

    /** @param resource $socket */
    private function runSameTokenChild($socket, int $actorId, int $variantId, string $token): never
    {
        $this->configureChildTimeout($socket);
        try {
            DB::purge('mysql_testing');
            $connection = $this->guardMySqlTestConnection();
            fwrite($socket, "ready\n");
            fflush($socket);
            if ($this->readLine($socket, 10) !== 'go') {
                throw new RuntimeException('The child did not receive the start signal.');
            }

            $connection->beginTransaction();
            $isolation = (string) $connection->scalar('SELECT @@transaction_isolation');
            $snapshotTokenCount = $connection->table('restocks')->where('submission_token', $token)->count();
            $snapshotStock = (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock');
            fwrite($socket, json_encode([
                'status' => 'snapshot-established', 'isolation' => $isolation,
                'token_count' => $snapshotTokenCount, 'stock' => $snapshotStock,
            ], JSON_THROW_ON_ERROR)."\n");
            fwrite($socket, "attempting\n");
            fflush($socket);

            $restock = app(RecordRestock::class)->execute(
                User::query()->findOrFail($actorId), $token, 'Same receipt', 'Equivalent retry',
                [['product_variant_id' => $variantId, 'quantity' => '5.000', 'unit_cost' => '11.00']],
            );
            $result = [
                'status' => 'replayed',
                'restock_id' => $restock->id,
                'stale_token_count' => $connection->table('restocks')->where('submission_token', $token)->count(),
                'stale_stock' => (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock'),
            ];
            $connection->rollBack();
            fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
            $this->finishChild($socket, 0);
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $this->failChild($socket, $exception);
        }
    }

    private function requireProcessSupport(): void
    {
        foreach (['pcntl_fork', 'pcntl_waitpid', 'stream_socket_pair'] as $function) {
            $this->assertTrue(function_exists($function), "{$function} is required for the MySQL concurrency proof.");
        }
    }

    /** @return array{resource, int} */
    private function fork(callable $childWork): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Unable to create the concurrency-test IPC channel.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);

            throw new RuntimeException('Unable to fork the Restock concurrency process.');
        }
        if ($pid === 0) {
            fclose($sockets[0]);
            $childWork($sockets[1]);
        }
        fclose($sockets[1]);

        return [$sockets[0], $pid];
    }

    /** @param resource $socket */
    private function configureChildTimeout($socket): void
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function () use ($socket): never {
            fwrite($socket, json_encode(['status' => 'timeout'], JSON_THROW_ON_ERROR)."\n");
            exit(2);
        });
        pcntl_alarm(25);
    }

    /** @param resource $socket */
    private function finishChild($socket, int $status): never
    {
        pcntl_alarm(0);
        DB::disconnect('mysql_testing');
        fclose($socket);
        exit($status);
    }

    /** @param resource $socket */
    private function failChild($socket, Throwable $exception): never
    {
        fwrite($socket, json_encode(['status' => 'exception', 'class' => $exception::class], JSON_THROW_ON_ERROR)."\n");
        $this->finishChild($socket, 3);
    }

    /** @param resource $socket */
    private function assertChildStillBlocked($socket, string $message): void
    {
        $read = [$socket];
        $write = null;
        $except = null;
        $this->assertSame(0, stream_select($read, $write, $except, 0, 500000), $message);
    }

    private function waitForSuccessfulChild(int $pid): void
    {
        $this->assertSame($pid, pcntl_waitpid($pid, $status));
        $this->assertTrue(pcntl_wifexited($status));
        $this->assertSame(0, pcntl_wexitstatus($status));
    }

    /** @param resource $socket */
    private function readLine($socket, int $timeoutSeconds): string
    {
        $read = [$socket];
        $write = null;
        $except = null;
        if (stream_select($read, $write, $except, $timeoutSeconds) !== 1) {
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
        $restockIds = DB::table('restock_items')->where('product_variant_id', $variantId)->pluck('restock_id');
        DB::table('restock_items')->where('product_variant_id', $variantId)->delete();
        DB::table('restocks')->whereIn('id', $restockIds)->delete();
        DB::table('product_variants')->where('id', $variantId)->delete();
        DB::table('products')->where('id', $productId)->delete();
        DB::table('categories')->where('id', $categoryId)->delete();
        DB::table('users')->where('id', $actorId)->delete();
    }
}

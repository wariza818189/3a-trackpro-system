<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordOpeningInventory;
use App\Services\Sales\RecordSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SaleConcurrencyTest extends MySqlTestCase
{
    public function test_concurrent_distinct_sales_serialize_and_prevent_overselling(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        $baseline = $this->domainCounts();
        [$actorId, $categories, $products, $variants] = $this->createFixture(['5.000']);
        $variantId = $variants[0];
        $tokenA = Str::uuid()->toString();
        $tokenB = Str::uuid()->toString();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runOversellChild($child, $actorId, $variantId, $tokenB));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $connection->beginTransaction();
            app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $tokenA, '100.00', [
                $this->line($variantId, '4.000'),
            ]);

            fwrite($socket, "go\n");
            fflush($socket);
            $snapshot = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('snapshot-established', $snapshot['status'] ?? null);
            $this->assertSame('REPEATABLE-READ', $snapshot['isolation'] ?? null);
            $this->assertSame('5.000', $snapshot['stock'] ?? null);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The competing Sale completed while A held hierarchy locks.');
            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('rejected', $result['status'] ?? null);
            $this->assertStringContainsString('Current availability is 1.000', $result['message'] ?? '');
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('1.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $this->assertSame(1, Sale::query()->whereIn('checkout_token', [$tokenA, $tokenB])->count());
            $this->assertSame(0, Sale::query()->where('checkout_token', $tokenB)->count());
            $this->assertSame(1, DB::table('sale_items')->where('product_variant_id', $variantId)->count());
            $this->assertSame(1, StockMovement::query()->where('product_variant_id', $variantId)->where('movement_type', StockMovement::TYPE_SALE)->count());
        } finally {
            $this->finishParent($socket, $pid, $childWaited, $connection ?? null);
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            $this->assertSame($baseline, $this->domainCounts());
        }
    }

    public function test_identical_same_token_race_recovers_winner_after_stale_snapshot(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        $baseline = $this->domainCounts();
        [$actorId, $categories, $products, $variants] = $this->createFixture(['10.000']);
        $variantId = $variants[0];
        $token = Str::uuid()->toString();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runSameTokenChild($child, $actorId, $variantId, $token));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $connection->beginTransaction();
            $winner = app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $token, '100.00', [
                $this->line($variantId, '4'),
            ]);

            fwrite($socket, "go\n");
            fflush($socket);
            $snapshot = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('snapshot-established', $snapshot['status'] ?? null);
            $this->assertSame('REPEATABLE-READ', $snapshot['isolation'] ?? null);
            $this->assertSame(0, $snapshot['token_count'] ?? null);
            $this->assertSame('10.000', $snapshot['stock'] ?? null);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The duplicate checkout did not wait for the winner.');
            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('replayed', $result['status'] ?? null);
            $this->assertSame($winner->id, $result['sale_id'] ?? null);
            $this->assertSame(0, $result['stale_token_count'] ?? null);
            $this->assertSame('10.000', $result['stale_stock'] ?? null);
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('6.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $this->assertSame(1, Sale::query()->where('checkout_token', $token)->count());
            $this->assertSame(1, DB::table('sale_items')->where('product_variant_id', $variantId)->count());
            $this->assertSame(1, StockMovement::query()->where('product_variant_id', $variantId)->where('movement_type', StockMovement::TYPE_SALE)->count());
        } finally {
            $this->finishParent($socket, $pid, $childWaited, $connection ?? null);
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            $this->assertSame($baseline, $this->domainCounts());
        }
    }

    public function test_reversed_multi_variant_browser_order_uses_global_hierarchy_order_without_deadlock(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        $baseline = $this->domainCounts();
        [$actorId, $categories, $products, $variants] = $this->createFixture(['10.000', '10.000']);
        [$firstId, $secondId] = $variants;
        $tokenA = Str::uuid()->toString();
        $tokenB = Str::uuid()->toString();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runReverseOrderChild($child, $actorId, $firstId, $secondId, $tokenB));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $connection->beginTransaction();
            app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $tokenA, '500.00', [
                $this->line($firstId, '2'),
                $this->line($secondId, '3'),
            ]);

            fwrite($socket, "go\n");
            fflush($socket);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The reversed-order Sale completed while A held hierarchy locks.');
            $connection->commit();
            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('completed', $result['status'] ?? null);
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('7.000', ProductVariant::query()->findOrFail($firstId)->current_stock);
            $this->assertSame('3.000', ProductVariant::query()->findOrFail($secondId)->current_stock);
            $this->assertSame(2, Sale::query()->whereIn('checkout_token', [$tokenA, $tokenB])->count());
            $this->assertSame(4, DB::table('sale_items')->whereIn('product_variant_id', $variants)->count());
            foreach ($variants as $variantId) {
                $movements = StockMovement::query()->where('product_variant_id', $variantId)
                    ->where('movement_type', StockMovement::TYPE_SALE)->orderBy('id')->get();
                $this->assertCount(2, $movements);
                $this->assertSame($movements[0]->quantity_after, $movements[1]->quantity_before);
            }
        } finally {
            $this->finishParent($socket, $pid, $childWaited, $connection ?? null);
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            $this->assertSame($baseline, $this->domainCounts());
        }
    }

    public function test_same_token_disjoint_inventory_blocks_on_unique_arbitration_and_rejects_semantic_reuse(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        $baseline = $this->domainCounts();
        [$actorId, $categories, $products, $variants] = $this->createFixture(['10.000', '10.000']);
        [$winnerVariantId, $loserVariantId] = $variants;
        $token = Str::uuid()->toString();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runDisjointTokenChild($child, $actorId, $loserVariantId, $token));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $connection->beginTransaction();
            app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $token, '100.00', [
                $this->line($winnerVariantId, '1'),
            ]);

            fwrite($socket, "go\n");
            fflush($socket);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'Disjoint inventory did not block at checkout-token arbitration.');
            $connection->commit();
            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('token-reuse-rejected', $result['status'] ?? null);
            $this->assertStringContainsString('already associated', $result['message'] ?? '');
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame(1, Sale::query()->where('checkout_token', $token)->count());
            $this->assertSame(1, DB::table('sale_items')->where('product_variant_id', $winnerVariantId)->count());
            $this->assertSame(0, DB::table('sale_items')->where('product_variant_id', $loserVariantId)->count());
            $this->assertSame('9.000', ProductVariant::query()->findOrFail($winnerVariantId)->current_stock);
            $this->assertSame('10.000', ProductVariant::query()->findOrFail($loserVariantId)->current_stock);
            $this->assertSame(0, StockMovement::query()->where('product_variant_id', $loserVariantId)->where('movement_type', StockMovement::TYPE_SALE)->count());
        } finally {
            $this->finishParent($socket, $pid, $childWaited, $connection ?? null);
            $this->cleanupFixture($actorId, $categories, $products, $variants);
            $this->assertSame($baseline, $this->domainCounts());
        }
    }

    /** @param resource $socket */
    private function runOversellChild($socket, int $actorId, int $variantId, string $token): never
    {
        $this->configureChildTimeout($socket);
        try {
            DB::purge('mysql_testing');
            $connection = $this->guardMySqlTestConnection();
            fwrite($socket, "ready\n");
            fflush($socket);
            $this->awaitGo($socket);
            $connection->beginTransaction();
            fwrite($socket, json_encode([
                'status' => 'snapshot-established',
                'isolation' => (string) $connection->scalar('SELECT @@transaction_isolation'),
                'stock' => (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock'),
            ], JSON_THROW_ON_ERROR)."\n");
            fwrite($socket, "attempting\n");
            fflush($socket);
            try {
                app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $token, '100.00', [$this->line($variantId, '4')]);
                $result = ['status' => 'unexpected-success'];
            } catch (ValidationException $exception) {
                $result = ['status' => 'rejected', 'message' => $exception->errors()['items.0.quantity'][0] ?? null];
            }
            $connection->rollBack();
            fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
            $this->finishChild($socket, $result['status'] === 'rejected' ? 0 : 1);
        } catch (Throwable $exception) {
            $this->rollbackChild($connection ?? null);
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
            $this->awaitGo($socket);
            $connection->beginTransaction();
            $snapshotTokenCount = $connection->table('sales')->where('checkout_token', $token)->count();
            $snapshotStock = (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock');
            fwrite($socket, json_encode([
                'status' => 'snapshot-established',
                'isolation' => (string) $connection->scalar('SELECT @@transaction_isolation'),
                'token_count' => $snapshotTokenCount,
                'stock' => $snapshotStock,
            ], JSON_THROW_ON_ERROR)."\n");
            fwrite($socket, "attempting\n");
            fflush($socket);
            $sale = app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $token, '100.00', [$this->line($variantId, '4.000')]);
            $result = [
                'status' => 'replayed',
                'sale_id' => $sale->id,
                'stale_token_count' => $snapshotTokenCount,
                'stale_stock' => $snapshotStock,
            ];
            $connection->rollBack();
            fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
            $this->finishChild($socket, 0);
        } catch (Throwable $exception) {
            $this->rollbackChild($connection ?? null);
            $this->failChild($socket, $exception);
        }
    }

    /** @param resource $socket */
    private function runReverseOrderChild($socket, int $actorId, int $firstId, int $secondId, string $token): never
    {
        $this->configureChildTimeout($socket);
        try {
            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            fwrite($socket, "ready\n");
            fflush($socket);
            $this->awaitGo($socket);
            fwrite($socket, "attempting\n");
            fflush($socket);
            $sale = app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $token, '500.00', [
                $this->line($secondId, '4'),
                $this->line($firstId, '1'),
            ]);
            fwrite($socket, json_encode(['status' => 'completed', 'sale_id' => $sale->id], JSON_THROW_ON_ERROR)."\n");
            $this->finishChild($socket, 0);
        } catch (Throwable $exception) {
            $this->failChild($socket, $exception);
        }
    }

    /** @param resource $socket */
    private function runDisjointTokenChild($socket, int $actorId, int $variantId, string $token): never
    {
        $this->configureChildTimeout($socket);
        try {
            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            fwrite($socket, "ready\n");
            fflush($socket);
            $this->awaitGo($socket);
            fwrite($socket, "attempting\n");
            fflush($socket);
            try {
                app(RecordSale::class)->execute(User::query()->findOrFail($actorId), $token, '100.00', [$this->line($variantId, '1')]);
                $result = ['status' => 'unexpected-success'];
            } catch (ValidationException $exception) {
                $result = ['status' => 'token-reuse-rejected', 'message' => $exception->errors()['submission_token'][0] ?? null];
            }
            fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
            $this->finishChild($socket, $result['status'] === 'token-reuse-rejected' ? 0 : 1);
        } catch (Throwable $exception) {
            $this->failChild($socket, $exception);
        }
    }

    /** @param list<string> $stocks @return array{int, list<int>, list<int>, list<int>} */
    private function createFixture(array $stocks): array
    {
        $this->guardMySqlTestConnection();

        return DB::connection('mysql_testing')->transaction(function () use ($stocks): array {
            $actor = new User;
            $actor->name = 'Sale Concurrency Cashier';
            $actor->username = 'sale_concurrency_'.bin2hex(random_bytes(8));
            $actor->password = 'not-used-for-login';
            $actor->role = User::ROLE_ADMIN;
            $actor->status = User::STATUS_ACTIVE;
            $actor->save();
            $categoryIds = [];
            $productIds = [];
            $variantIds = [];
            foreach ($stocks as $offset => $stock) {
                $category = new Category(['name' => 'Sale concurrency '.bin2hex(random_bytes(6))]);
                $category->status = Category::STATUS_ACTIVE;
                $category->save();
                $product = new Product(['name' => 'Concurrent sale item '.$offset]);
                $product->category_id = $category->id;
                $product->status = Product::STATUS_ACTIVE;
                $product->save();
                $variant = new ProductVariant([
                    'size' => 'Fixture '.$offset, 'type_series' => '', 'thickness' => '', 'unit' => 'piece',
                    'quantity_mode' => 'whole', 'cost_price' => '50.00', 'selling_price' => '25.00',
                    'low_stock_threshold' => '0.000',
                ]);
                $variant->product_id = $product->id;
                $variant->status = ProductVariant::STATUS_ACTIVE;
                $variant->save();
                app(RecordOpeningInventory::class)->execute($variant, $actor, $stock, 'Sale concurrency fixture');
                $categoryIds[] = $category->id;
                $productIds[] = $product->id;
                $variantIds[] = $variant->id;
            }

            return [$actor->id, $categoryIds, $productIds, $variantIds];
        });
    }

    /** @return array{product_variant_id: int, quantity: string, expected_unit_price: string} */
    private function line(int $variantId, string $quantity): array
    {
        return ['product_variant_id' => $variantId, 'quantity' => $quantity, 'expected_unit_price' => '25.00'];
    }

    /** @return array<string, int> */
    private function domainCounts(): array
    {
        DB::purge('mysql_testing');
        $this->guardMySqlTestConnection();
        $counts = [];
        foreach (['users', 'categories', 'products', 'product_variants', 'sales', 'sale_items', 'restocks', 'restock_items', 'stock_movements', 'audit_logs'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /** @param list<int> $categoryIds @param list<int> $productIds @param list<int> $variantIds */
    private function cleanupFixture(int $actorId, array $categoryIds, array $productIds, array $variantIds): void
    {
        DB::purge('mysql_testing');
        $this->guardMySqlTestConnection();
        $saleIds = DB::table('sales')->where('recorded_by', $actorId)->pluck('id');
        $saleItemIds = DB::table('sale_items')->whereIn('sale_id', $saleIds)->pluck('id');
        DB::table('stock_movements')
            ->whereIn('product_variant_id', $variantIds)
            ->orWhereIn('sale_item_id', $saleItemIds)
            ->orWhere('performed_by', $actorId)
            ->delete();
        DB::table('sale_items')
            ->whereIn('product_variant_id', $variantIds)
            ->orWhereIn('sale_id', $saleIds)
            ->delete();
        DB::table('sales')->where('recorded_by', $actorId)->delete();
        DB::table('audit_logs')->where('user_id', $actorId)->delete();
        DB::table('product_variants')->whereIn('id', $variantIds)->delete();
        DB::table('products')->whereIn('id', $productIds)->delete();
        DB::table('categories')->whereIn('id', $categoryIds)->delete();
        DB::table('users')->where('id', $actorId)->delete();
    }

    private function requireProcessSupport(): void
    {
        foreach (['pcntl_fork', 'pcntl_waitpid', 'stream_socket_pair'] as $function) {
            $this->assertTrue(function_exists($function), "{$function} is required for the MySQL Sale concurrency proof.");
        }
    }

    /** @return array{resource, int} */
    private function fork(callable $childWork): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Unable to create the Sale concurrency IPC channel.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);
            throw new RuntimeException('Unable to fork the Sale concurrency process.');
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
    private function awaitGo($socket): void
    {
        if ($this->readLine($socket, 10) !== 'go') {
            throw new RuntimeException('The child did not receive the start signal.');
        }
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

    private function rollbackChild($connection): void
    {
        if ($connection !== null && $connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
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
            throw new RuntimeException('Timed out waiting for the Sale concurrency process.');
        }
        $line = fgets($socket);
        if ($line === false) {
            throw new RuntimeException('The Sale concurrency IPC channel closed unexpectedly.');
        }

        return trim($line);
    }

    /** @param resource $socket */
    private function finishParent($socket, int $pid, bool $childWaited, $connection): void
    {
        if ($connection !== null && $connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        fclose($socket);
        if (! $childWaited) {
            pcntl_waitpid($pid, $status);
        }
    }
}

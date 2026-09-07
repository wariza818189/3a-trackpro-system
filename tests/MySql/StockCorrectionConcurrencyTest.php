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
use App\Services\Inventory\RecordStockCorrection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class StockCorrectionConcurrencyTest extends MySqlTestCase
{
    public function test_different_targets_serialize_and_stale_loser_cannot_overwrite_winner(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        [$actorId, $categoryId, $productId, $variantId, $openingMovementId] = $this->createCommittedInitializedFixture();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runCorrectionChild(
                $child, $actorId, $variantId, $openingMovementId, '12', 'Competing target B',
            ));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);

            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $variant = ProductVariant::query()->with('product:id,category_id')->findOrFail($variantId);
            $actor = User::query()->findOrFail($actorId);
            $connection->beginTransaction();
            app(RecordStockCorrection::class)->execute(
                $variant, $actor, '7', $openingMovementId, 'Winning target A',
            );

            fwrite($socket, "go\n");
            fflush($socket);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The competing correction completed while A held the hierarchy locks.');
            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('rejected', $result['status'] ?? null);
            $this->assertSame(
                'Stock changed while this correction was being prepared. Review the latest stock and try again.',
                $result['message'] ?? null,
            );
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('7.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $movement = StockMovement::query()
                ->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_CORRECTION)
                ->sole();
            $this->assertSame('10.000', $movement->quantity_before);
            $this->assertSame('-3.000', $movement->quantity_change);
            $this->assertSame('7.000', $movement->quantity_after);
            $this->assertSame('Winning target A', $movement->reason);
        } finally {
            $this->finishParent($socket, $pid, $childWaited, $connection ?? null);
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);
        }
    }

    public function test_same_target_serializes_and_loser_is_rejected_as_no_op(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        [$actorId, $categoryId, $productId, $variantId, $openingMovementId] = $this->createCommittedInitializedFixture();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runCorrectionChild(
                $child, $actorId, $variantId, $openingMovementId, '7', 'Same target B',
            ));
        } catch (Throwable $exception) {
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);

            throw $exception;
        }
        $childWaited = false;

        try {
            $this->assertSame('ready', $this->readLine($socket, 10));
            $connection = $this->guardMySqlTestConnection();
            $variant = ProductVariant::query()->with('product:id,category_id')->findOrFail($variantId);
            $actor = User::query()->findOrFail($actorId);
            $connection->beginTransaction();
            app(RecordStockCorrection::class)->execute(
                $variant, $actor, '7', $openingMovementId, 'Same target A',
            );

            fwrite($socket, "go\n");
            fflush($socket);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The same-target correction completed while A held the hierarchy locks.');
            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('rejected', $result['status'] ?? null);
            $this->assertSame('No stock change is required.', $result['message'] ?? null);
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('7.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $this->assertSame(1, StockMovement::query()
                ->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_CORRECTION)
                ->count());
        } finally {
            $this->finishParent($socket, $pid, $childWaited, $connection ?? null);
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);
        }
    }

    public function test_restock_winner_makes_waiting_correction_stale_without_received_stock_overwrite(): void
    {
        $this->requireProcessSupport();
        DB::connection('mysql_testing')->rollBack();
        [$actorId, $categoryId, $productId, $variantId, $openingMovementId] = $this->createCommittedInitializedFixture();
        DB::purge('mysql_testing');

        try {
            [$socket, $pid] = $this->fork(fn ($child): never => $this->runCorrectionChild(
                $child, $actorId, $variantId, $openingMovementId, '7', 'Stale physical target',
            ));
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
            app(RecordRestock::class)->execute(
                $actor,
                Str::uuid()->toString(),
                'Concurrent receipt',
                null,
                [['product_variant_id' => $variantId, 'quantity' => '5', 'unit_cost' => '11']],
            );

            fwrite($socket, "go\n");
            fflush($socket);
            $this->assertSame('attempting', $this->readLine($socket, 10));
            $this->assertChildStillBlocked($socket, 'The correction completed while Restock held the hierarchy locks.');
            $connection->commit();

            $result = json_decode($this->readLine($socket, 10), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('rejected', $result['status'] ?? null);
            $this->assertSame(
                'Stock changed while this correction was being prepared. Review the latest stock and try again.',
                $result['message'] ?? null,
            );
            $this->waitForSuccessfulChild($pid);
            $childWaited = true;

            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            $this->assertSame('15.000', ProductVariant::query()->findOrFail($variantId)->current_stock);
            $restockMovement = StockMovement::query()
                ->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_RESTOCK)
                ->sole();
            $this->assertSame('10.000', $restockMovement->quantity_before);
            $this->assertSame('5.000', $restockMovement->quantity_change);
            $this->assertSame('15.000', $restockMovement->quantity_after);
            $this->assertSame(0, StockMovement::query()
                ->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_CORRECTION)
                ->count());
            $this->assertSame(1, Restock::query()->where('reference_text', 'Concurrent receipt')->count());
        } finally {
            $this->finishParent($socket, $pid, $childWaited, $connection ?? null);
            $this->cleanupFixture($actorId, $categoryId, $productId, $variantId);
        }
    }

    /** @return array{int, int, int, int, int} */
    private function createCommittedInitializedFixture(): array
    {
        $this->guardMySqlTestConnection();

        return DB::connection('mysql_testing')->transaction(function (): array {
            $actor = new User;
            $actor->name = 'Stock Correction Concurrency Admin';
            $actor->username = 'stock_correction_concurrency_'.bin2hex(random_bytes(8));
            $actor->password = 'not-used-for-login';
            $actor->role = User::ROLE_ADMIN;
            $actor->status = User::STATUS_ACTIVE;
            $actor->save();

            $category = new Category(['name' => 'Correction concurrency '.bin2hex(random_bytes(6))]);
            $category->status = Category::STATUS_ACTIVE;
            $category->save();
            $product = new Product(['name' => 'Concurrent physical count']);
            $product->category_id = $category->id;
            $product->status = Product::STATUS_ACTIVE;
            $product->save();
            $variant = new ProductVariant([
                'size' => 'Concurrent item',
                'type_series' => '',
                'thickness' => '',
                'unit' => 'piece',
                'quantity_mode' => 'whole',
                'cost_price' => '10.00',
                'selling_price' => '20.00',
                'low_stock_threshold' => '0.000',
            ]);
            $variant->product_id = $product->id;
            $variant->status = ProductVariant::STATUS_ACTIVE;
            $variant->save();

            $opening = app(RecordOpeningInventory::class)->execute(
                $variant, $actor, '10', 'Concurrency fixture opening count',
            );

            return [$actor->id, $category->id, $product->id, $variant->id, $opening->id];
        });
    }

    /** @param resource $socket */
    private function runCorrectionChild(
        $socket,
        int $actorId,
        int $variantId,
        int $expectedMovementId,
        string $target,
        string $reason,
    ): never {
        $this->configureChildTimeout($socket);

        try {
            DB::purge('mysql_testing');
            $this->guardMySqlTestConnection();
            fwrite($socket, "ready\n");
            fflush($socket);
            if ($this->readLine($socket, 10) !== 'go') {
                throw new RuntimeException('The child did not receive the start signal.');
            }

            $variant = ProductVariant::query()->with('product:id,category_id')->findOrFail($variantId);
            $actor = User::query()->findOrFail($actorId);
            fwrite($socket, "attempting\n");
            fflush($socket);

            try {
                app(RecordStockCorrection::class)->execute(
                    $variant, $actor, $target, $expectedMovementId, $reason,
                );
                $result = ['status' => 'unexpected-success'];
            } catch (ValidationException $exception) {
                $result = [
                    'status' => 'rejected',
                    'message' => $exception->errors()['corrected_stock'][0] ?? null,
                ];
            }

            fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
            $this->finishChild($socket, $result['status'] === 'rejected' ? 0 : 1);
        } catch (Throwable $exception) {
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
            throw new RuntimeException('Unable to create the Stock Correction concurrency IPC channel.');
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);

            throw new RuntimeException('Unable to fork the Stock Correction concurrency process.');
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

    /** @param resource $socket */
    private function finishParent($socket, int $pid, bool $childWaited, mixed $connection): void
    {
        if ($connection !== null && $connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        fclose($socket);
        if (! $childWaited) {
            pcntl_waitpid($pid, $status);
        }
    }

    private function cleanupFixture(int $actorId, int $categoryId, int $productId, int $variantId): void
    {
        DB::purge('mysql_testing');
        $this->guardMySqlTestConnection();
        $restockIds = DB::table('restock_items')->where('product_variant_id', $variantId)->pluck('restock_id');
        DB::table('stock_movements')->where('product_variant_id', $variantId)->delete();
        DB::table('restock_items')->where('product_variant_id', $variantId)->delete();
        DB::table('restocks')->whereIn('id', $restockIds)->delete();
        DB::table('product_variants')->where('id', $variantId)->delete();
        DB::table('products')->where('id', $productId)->delete();
        DB::table('categories')->where('id', $categoryId)->delete();
        DB::table('users')->where('id', $actorId)->delete();
    }
}

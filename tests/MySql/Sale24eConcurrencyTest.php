<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
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

final class Sale24eConcurrencyTest extends MySql24eConcurrencyTestCase
{
    private const INSUFFICIENT_STOCK_MESSAGE = 'Insufficient stock. Current availability is 1.000 piece.';

    private const TOKEN_CONFLICT_MESSAGE = 'This checkout token is already associated with a different sale. Review the cart and try again.';

    /** @var list<string> */
    private const DOMAIN_TABLES = [
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

    public function test_concurrent_distinct_sales_serialize_and_cannot_oversell_shared_stock(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;
        $parentTransactionActive = false;
        $tokens = [];

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createSaleFixture($connection, ['5.000']);
            $this->assertActiveSession($connection, $fixture);

            $variantId = $fixture['variant_ids'][0];
            $actorId = $fixture['actor_id'];
            $parentToken = $this->checkoutToken();
            $childToken = $this->checkoutToken();
            $tokens = [$parentToken, $childToken];
            $this->assertSame(0, $connection->table('sales')->whereIn('checkout_token', $tokens)->count());
            unset($connection);

            [$socket, $pid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runOversellWorker(
                    $socket,
                    $connection,
                    $actorId,
                    $variantId,
                    $childToken,
                ),
            );
            $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
            $parentConnection = $this->reconnectParentAfterFork();

            $this->assertSame('ready', $this->readStatus($socket));
            $parentConnection->beginTransaction();
            $parentTransactionActive = true;
            $actor = User::query()->findOrFail($actorId);
            $parentSale = $this->recordSaleSafely(
                $actor,
                $parentToken,
                '100.00',
                [$this->saleLine($variantId, '4')],
            );
            $this->assertSame($fixture['session_id'], (int) $parentSale->cash_register_session_id);

            $this->writeStatus($socket, 'go');
            $snapshot = $this->readResult($socket);
            $this->assertSame('snapshot-established', $snapshot['status']);
            $this->assertSame(['stock' => '5.000'], $snapshot['data']);
            $this->assertSame('attempting', $this->readStatus($socket));
            $this->assertNoWorkerResult(
                $socket,
                'The competing sale completed before the winning sale released its inventory locks.',
            );

            $parentConnection->commit();
            $parentTransactionActive = false;
            $result = $this->readResult($socket);
            $this->assertSame('insufficient-stock', $result['status']);
            $this->assertSame([], $result['data']);
            $this->joinWorkerSuccessfully($workers[0]);

            $parentConnection = $this->reconnectParentAfterFork();
            $this->assertSame(1, $parentConnection->table('sales')->where('checkout_token', $parentToken)->count());
            $this->assertSame(0, $parentConnection->table('sales')->where('checkout_token', $childToken)->count());
            $this->assertSame(1, $parentConnection->table('sales')->whereIn('checkout_token', $tokens)->count());
            $persistedSale = $parentConnection->table('sales')->where('checkout_token', $parentToken)->first();
            $this->assertNotNull($persistedSale);
            $this->assertSame((int) $parentSale->getKey(), (int) $persistedSale->id);
            $this->assertSame($fixture['session_id'], (int) $persistedSale->cash_register_session_id);

            $item = $parentConnection->table('sale_items')->where('sale_id', $persistedSale->id)->first();
            $this->assertNotNull($item);
            $this->assertSame(1, $parentConnection->table('sale_items')->where('sale_id', $persistedSale->id)->count());
            $this->assertSame($variantId, (int) $item->product_variant_id);
            $this->assertSame('4.000', (string) $item->quantity);

            $movement = $parentConnection->table('stock_movements')
                ->where('sale_item_id', $item->id)
                ->where('movement_type', StockMovement::TYPE_SALE)
                ->first();
            $this->assertNotNull($movement);
            $this->assertSame(
                1,
                $parentConnection->table('stock_movements')
                    ->where('sale_item_id', $item->id)
                    ->where('movement_type', StockMovement::TYPE_SALE)
                    ->count(),
            );
            $this->assertSame('5.000', (string) $movement->quantity_before);
            $this->assertSame('-4.000', (string) $movement->quantity_change);
            $this->assertSame('1.000', (string) $movement->quantity_after);
            $this->assertSame('1.000', $this->variantStock($parentConnection, $variantId));
            $this->assertInitialStockCount($parentConnection, $variantId);
            $this->assertActiveSession($parentConnection, $fixture);
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                $tokens,
                $baseline,
                $parentTransactionActive,
            );
        }
    }

    public function test_identical_same_token_race_recovers_committed_sale_from_stale_snapshot(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;
        $parentTransactionActive = false;
        $tokens = [];

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createSaleFixture($connection, ['10.000']);
            $this->assertActiveSession($connection, $fixture);

            $variantId = $fixture['variant_ids'][0];
            $actorId = $fixture['actor_id'];
            $token = $this->checkoutToken();
            $tokens = [$token];
            $this->assertSame(0, $connection->table('sales')->where('checkout_token', $token)->count());
            unset($connection);

            [$socket, $pid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runSameTokenWorker(
                    $socket,
                    $connection,
                    $actorId,
                    $variantId,
                    $token,
                ),
            );
            $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
            $parentConnection = $this->reconnectParentAfterFork();

            $this->assertSame('ready', $this->readStatus($socket));
            $parentConnection->beginTransaction();
            $parentTransactionActive = true;
            $actor = User::query()->findOrFail($actorId);
            $parentSale = $this->recordSaleSafely(
                $actor,
                $token,
                '100.00',
                [$this->saleLine($variantId, '4')],
            );
            $parentSaleId = (int) $parentSale->getKey();
            $parentItemIds = $this->saleItemIds($parentConnection, $parentSaleId);
            $parentMovementIds = $this->saleMovementIds($parentConnection, $parentItemIds);
            $this->assertCount(1, $parentItemIds);
            $this->assertCount(1, $parentMovementIds);

            $this->writeStatus($socket, 'go');
            $snapshot = $this->readResult($socket);
            $this->assertSame('snapshot-established', $snapshot['status']);
            $this->assertSame(['token_count' => 0, 'stock' => '10.000'], $snapshot['data']);
            $this->assertSame('attempting', $this->readStatus($socket));
            $this->assertNoWorkerResult(
                $socket,
                'The same-token contender completed before the winning sale committed.',
            );

            $parentConnection->commit();
            $parentTransactionActive = false;
            $result = $this->readResult($socket);
            $this->assertSame('same-sale-replay', $result['status']);
            $this->assertSame(['sale_id', 'session_id'], array_keys($result['data']));
            $this->assertSame($parentSaleId, $result['data']['sale_id']);
            $this->assertSame($fixture['session_id'], $result['data']['session_id']);
            $this->joinWorkerSuccessfully($workers[0]);

            $parentConnection = $this->reconnectParentAfterFork();
            $this->assertSame(1, $parentConnection->table('sales')->where('checkout_token', $token)->count());
            $persistedSale = $parentConnection->table('sales')->where('checkout_token', $token)->first();
            $this->assertNotNull($persistedSale);
            $this->assertSame($parentSaleId, (int) $persistedSale->id);
            $this->assertSame($fixture['session_id'], (int) $persistedSale->cash_register_session_id);

            $freshItemIds = $this->saleItemIds($parentConnection, $parentSaleId);
            $freshMovementIds = $this->saleMovementIds($parentConnection, $freshItemIds);
            $this->assertSame($parentItemIds, $freshItemIds);
            $this->assertSame($parentMovementIds, $freshMovementIds);
            $this->assertCount(1, $freshItemIds);
            $this->assertCount(1, $freshMovementIds);
            $item = $parentConnection->table('sale_items')->where('id', $freshItemIds[0])->first();
            $movement = $parentConnection->table('stock_movements')->where('id', $freshMovementIds[0])->first();
            $this->assertNotNull($item);
            $this->assertNotNull($movement);
            $this->assertSame('4.000', (string) $item->quantity);
            $this->assertSame('10.000', (string) $movement->quantity_before);
            $this->assertSame('-4.000', (string) $movement->quantity_change);
            $this->assertSame('6.000', (string) $movement->quantity_after);
            $this->assertSame('6.000', $this->variantStock($parentConnection, $variantId));
            $this->assertInitialStockCount($parentConnection, $variantId);
            $this->assertActiveSession($parentConnection, $fixture);
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                $tokens,
                $baseline,
                $parentTransactionActive,
            );
        }
    }

    public function test_reversed_multi_variant_requests_complete_without_deadlock_and_preserve_stock(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;
        $parentTransactionActive = false;
        $tokens = [];

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createSaleFixture($connection, ['10.000', '10.000']);
            $this->assertActiveSession($connection, $fixture);

            [$variantA, $variantB] = $fixture['variant_ids'];
            $actorId = $fixture['actor_id'];
            $parentToken = $this->checkoutToken();
            $childToken = $this->checkoutToken();
            $tokens = [$parentToken, $childToken];
            $this->assertSame(0, $connection->table('sales')->whereIn('checkout_token', $tokens)->count());
            unset($connection);

            [$socket, $pid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runReverseOrderWorker(
                    $socket,
                    $connection,
                    $actorId,
                    $variantA,
                    $variantB,
                    $childToken,
                ),
            );
            $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
            $parentConnection = $this->reconnectParentAfterFork();

            $this->assertSame('ready', $this->readStatus($socket));
            $parentConnection->beginTransaction();
            $parentTransactionActive = true;
            $actor = User::query()->findOrFail($actorId);
            $parentSale = $this->recordSaleSafely(
                $actor,
                $parentToken,
                '500.00',
                [
                    $this->saleLine($variantA, '2'),
                    $this->saleLine($variantB, '3'),
                ],
            );

            $this->writeStatus($socket, 'go');
            $this->assertSame('attempting', $this->readStatus($socket));
            $this->assertNoWorkerResult(
                $socket,
                'The reverse-order sale completed before the first sale released its hierarchy locks.',
            );
            $parentConnection->commit();
            $parentTransactionActive = false;

            $result = $this->readResult($socket);
            $this->assertSame('sale-success', $result['status']);
            $this->assertSame(['sale_id', 'session_id'], array_keys($result['data']));
            $this->assertSame($fixture['session_id'], $result['data']['session_id']);
            $this->joinWorkerSuccessfully($workers[0]);

            $parentConnection = $this->reconnectParentAfterFork();
            $sales = $parentConnection->table('sales')
                ->whereIn('checkout_token', $tokens)
                ->orderBy('id')
                ->get()
                ->keyBy('checkout_token');
            $this->assertCount(2, $sales);
            $this->assertSame((int) $parentSale->getKey(), (int) $sales[$parentToken]->id);
            $this->assertSame($result['data']['sale_id'], (int) $sales[$childToken]->id);
            $this->assertSame($fixture['session_id'], (int) $sales[$parentToken]->cash_register_session_id);
            $this->assertSame($fixture['session_id'], (int) $sales[$childToken]->cash_register_session_id);

            $parentSaleId = (int) $sales[$parentToken]->id;
            $childSaleId = (int) $sales[$childToken]->id;
            $this->assertSame(2, $parentConnection->table('sale_items')->where('sale_id', $parentSaleId)->count());
            $this->assertSame(2, $parentConnection->table('sale_items')->where('sale_id', $childSaleId)->count());
            $allItemIds = $this->saleItemIds($parentConnection, $parentSaleId, $childSaleId);
            $this->assertCount(4, $allItemIds);
            $this->assertCount(4, $this->saleMovementIds($parentConnection, $allItemIds));

            $variantAMovements = $this->saleMovementsForVariant($parentConnection, $variantA);
            $variantBMovements = $this->saleMovementsForVariant($parentConnection, $variantB);
            $this->assertCount(2, $variantAMovements);
            $this->assertCount(2, $variantBMovements);
            $this->assertMovement($variantAMovements[0], '10.000', '-2.000', '8.000');
            $this->assertMovement($variantAMovements[1], '8.000', '-1.000', '7.000');
            $this->assertSame(
                (string) $variantAMovements[0]->quantity_after,
                (string) $variantAMovements[1]->quantity_before,
            );
            $this->assertMovement($variantBMovements[0], '10.000', '-3.000', '7.000');
            $this->assertMovement($variantBMovements[1], '7.000', '-4.000', '3.000');
            $this->assertSame(
                (string) $variantBMovements[0]->quantity_after,
                (string) $variantBMovements[1]->quantity_before,
            );
            $this->assertSame('7.000', $this->variantStock($parentConnection, $variantA));
            $this->assertSame('3.000', $this->variantStock($parentConnection, $variantB));
            $this->assertInitialStockCount($parentConnection, $variantA);
            $this->assertInitialStockCount($parentConnection, $variantB);
            $this->assertActiveSession($parentConnection, $fixture);
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                $tokens,
                $baseline,
                $parentTransactionActive,
            );
        }
    }

    public function test_different_same_token_request_is_rejected_without_extra_mutation(): void
    {
        $workers = [];
        $fixture = null;
        $baseline = null;
        $parentConnection = null;
        $parentTransactionActive = false;
        $tokens = [];

        try {
            $connection = $this->guardedConcurrencyConnection();
            $baseline = $this->domainCounts($connection);
            $this->assertSame(0, $this->activeRegisterCount($connection));
            $fixture = $this->createSaleFixture($connection, ['10.000', '10.000']);
            $this->assertActiveSession($connection, $fixture);

            [$variantA, $variantB] = $fixture['variant_ids'];
            $actorId = $fixture['actor_id'];
            $token = $this->checkoutToken();
            $tokens = [$token];
            $this->assertSame(0, $connection->table('sales')->where('checkout_token', $token)->count());
            unset($connection);

            [$socket, $pid] = $this->forkGuardedWorker(
                fn ($socket, Connection $connection): int => $this->runTokenConflictWorker(
                    $socket,
                    $connection,
                    $actorId,
                    $variantB,
                    $token,
                ),
            );
            $workers[] = ['socket' => $socket, 'pid' => $pid, 'joined' => false];
            $parentConnection = $this->reconnectParentAfterFork();

            $this->assertSame('ready', $this->readStatus($socket));
            $parentConnection->beginTransaction();
            $parentTransactionActive = true;
            $actor = User::query()->findOrFail($actorId);
            $parentSale = $this->recordSaleSafely(
                $actor,
                $token,
                '100.00',
                [$this->saleLine($variantA, '1')],
            );

            $this->writeStatus($socket, 'go');
            $this->assertSame('attempting', $this->readStatus($socket));
            // Production may serialize first on the active session, so this proves only non-completion.
            $this->assertNoWorkerResult(
                $socket,
                'The conflicting same-token request completed before the winning sale committed.',
            );
            $parentConnection->commit();
            $parentTransactionActive = false;

            $result = $this->readResult($socket);
            $this->assertSame('token-conflict', $result['status']);
            $this->assertSame([], $result['data']);
            $this->joinWorkerSuccessfully($workers[0]);

            $parentConnection = $this->reconnectParentAfterFork();
            $this->assertSame(1, $parentConnection->table('sales')->where('checkout_token', $token)->count());
            $persistedSale = $parentConnection->table('sales')->where('checkout_token', $token)->first();
            $this->assertNotNull($persistedSale);
            $this->assertSame((int) $parentSale->getKey(), (int) $persistedSale->id);
            $this->assertSame($fixture['session_id'], (int) $persistedSale->cash_register_session_id);

            $items = $parentConnection->table('sale_items')->where('sale_id', $persistedSale->id)->get();
            $this->assertCount(1, $items);
            $this->assertSame($variantA, (int) $items[0]->product_variant_id);
            $this->assertSame('1.000', (string) $items[0]->quantity);
            $this->assertSame(0, $parentConnection->table('sale_items')->where('product_variant_id', $variantB)->count());
            $this->assertSame(
                1,
                $parentConnection->table('stock_movements')
                    ->where('product_variant_id', $variantA)
                    ->where('movement_type', StockMovement::TYPE_SALE)
                    ->count(),
            );
            $this->assertSame(
                0,
                $parentConnection->table('stock_movements')
                    ->where('product_variant_id', $variantB)
                    ->where('movement_type', StockMovement::TYPE_SALE)
                    ->count(),
            );
            $this->assertSame('9.000', $this->variantStock($parentConnection, $variantA));
            $this->assertSame('10.000', $this->variantStock($parentConnection, $variantB));
            $this->assertInitialStockCount($parentConnection, $variantA);
            $this->assertInitialStockCount($parentConnection, $variantB);
            $this->assertActiveSession($parentConnection, $fixture);
        } finally {
            $this->finishScenario(
                $workers,
                $parentConnection,
                $fixture,
                $tokens,
                $baseline,
                $parentTransactionActive,
            );
        }
    }

    private function runOversellWorker(
        $socket,
        Connection $connection,
        int $actorId,
        int $variantId,
        string $token,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        $this->awaitGo($socket);

        $connection->beginTransaction();
        $stock = (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock');
        $this->writeResult($socket, 'snapshot-established', ['stock' => $stock]);
        $this->writeStatus($socket, 'attempting');

        try {
            $this->recordSaleSafely(
                $actor,
                $token,
                '100.00',
                [$this->saleLine($variantId, '4')],
            );
        } catch (ValidationException $exception) {
            $this->assertExactValidation(
                $exception,
                'items.0.quantity',
                self::INSUFFICIENT_STOCK_MESSAGE,
            );
            $connection->rollBack();
            $this->writeResult($socket, 'insufficient-stock');

            return self::CHILD_EXIT_SUCCESS;
        }

        $connection->rollBack();
        $this->writeResult($socket, 'unexpected-sale');

        return self::CHILD_EXIT_UNEXPECTED;
    }

    private function runSameTokenWorker(
        $socket,
        Connection $connection,
        int $actorId,
        int $variantId,
        string $token,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        $this->awaitGo($socket);

        $connection->beginTransaction();
        $tokenCount = $connection->table('sales')->where('checkout_token', $token)->count();
        $stock = (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock');
        $this->writeResult($socket, 'snapshot-established', [
            'token_count' => $tokenCount,
            'stock' => $stock,
        ]);
        $this->writeStatus($socket, 'attempting');

        $sale = $this->recordSaleSafely(
            $actor,
            $token,
            '100.00',
            [$this->saleLine($variantId, '4')],
        );
        $result = [
            'sale_id' => (int) $sale->getKey(),
            'session_id' => (int) $sale->cash_register_session_id,
        ];
        $connection->rollBack();
        $this->writeResult($socket, 'same-sale-replay', $result);

        return self::CHILD_EXIT_SUCCESS;
    }

    private function runReverseOrderWorker(
        $socket,
        Connection $connection,
        int $actorId,
        int $variantA,
        int $variantB,
        string $token,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        $this->awaitGo($socket);
        $this->writeStatus($socket, 'attempting');

        $sale = $this->recordSaleSafely(
            $actor,
            $token,
            '500.00',
            [
                $this->saleLine($variantB, '4'),
                $this->saleLine($variantA, '1'),
            ],
        );
        $this->writeResult($socket, 'sale-success', [
            'sale_id' => (int) $sale->getKey(),
            'session_id' => (int) $sale->cash_register_session_id,
        ]);

        return self::CHILD_EXIT_SUCCESS;
    }

    private function runTokenConflictWorker(
        $socket,
        Connection $connection,
        int $actorId,
        int $variantId,
        string $token,
    ): int {
        $this->guardWorkerConnection($connection);
        $actor = User::query()->findOrFail($actorId);
        $this->writeStatus($socket, 'ready');
        $this->awaitGo($socket);
        $this->writeStatus($socket, 'attempting');

        try {
            $this->recordSaleSafely(
                $actor,
                $token,
                '100.00',
                [$this->saleLine($variantId, '1')],
            );
        } catch (ValidationException $exception) {
            $this->assertExactValidation(
                $exception,
                'submission_token',
                self::TOKEN_CONFLICT_MESSAGE,
            );
            $this->writeResult($socket, 'token-conflict');

            return self::CHILD_EXIT_SUCCESS;
        }

        $this->writeResult($socket, 'unexpected-sale');

        return self::CHILD_EXIT_UNEXPECTED;
    }

    /**
     * @param  list<string>  $stocks
     * @return array<string, mixed>
     */
    private function createSaleFixture(Connection $connection, array $stocks): array
    {
        $marker = '24e_sale_concurrency_'.bin2hex(random_bytes(6));

        return $connection->transaction(function () use ($marker, $stocks): array {
            $actor = new User;
            $actor->name = $marker.'_admin';
            $actor->username = $marker.'_admin';
            $actor->password = 'not-used-for-login';
            $actor->role = User::ROLE_ADMIN;
            $actor->status = User::STATUS_ACTIVE;
            $actor->save();

            $categoryIds = [];
            $productIds = [];
            $variantIds = [];
            foreach ($stocks as $index => $stock) {
                $category = new Category(['name' => $marker.'_category_'.$index]);
                $category->status = Category::STATUS_ACTIVE;
                $category->save();

                $product = new Product(['name' => $marker.'_product_'.$index]);
                $product->category_id = $category->getKey();
                $product->status = Product::STATUS_ACTIVE;
                $product->save();

                $variant = new ProductVariant([
                    'size' => $marker.'_'.$index,
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
                    $actor,
                    $stock,
                    '24E dedicated Sale concurrency fixture opening count',
                );
                $categoryIds[] = (int) $category->getKey();
                $productIds[] = (int) $product->getKey();
                $variantIds[] = (int) $variant->getKey();
            }

            $session = app(OpenCashRegister::class)->execute($actor, '0.00');

            return [
                'marker' => $marker,
                'user_ids' => [(int) $actor->getKey()],
                'category_ids' => $categoryIds,
                'product_ids' => $productIds,
                'variant_ids' => $variantIds,
                'session_ids' => [(int) $session->getKey()],
                'actor_id' => (int) $actor->getKey(),
                'session_id' => (int) $session->getKey(),
            ];
        });
    }

    /** @return array{product_variant_id: int, quantity: string, expected_unit_price: string} */
    private function saleLine(int $variantId, string $quantity): array
    {
        return [
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'expected_unit_price' => '25.00',
        ];
    }

    /** @param list<array{product_variant_id: int, quantity: string, expected_unit_price: string}> $items */
    private function recordSaleSafely(User $actor, string $token, string $tender, array $items): Sale
    {
        try {
            return app(RecordSale::class)->execute($actor, $token, $tender, $items);
        } catch (QueryException) {
            throw new AssertionFailedError('RecordSale exposed an unexpected database failure safely.');
        }
    }

    private function assertExactValidation(
        ValidationException $exception,
        string $field,
        string $message,
    ): void {
        $errors = $exception->errors();
        if (array_keys($errors) !== [$field] || ($errors[$field][0] ?? null) !== $message) {
            throw $exception;
        }
    }

    private function awaitGo($socket): void
    {
        if ($this->readStatus($socket) !== 'go') {
            throw new RuntimeException('The dedicated Sale worker did not receive the start barrier.');
        }
    }

    private function guardWorkerConnection(Connection $connection): void
    {
        if ($connection->getName() !== self::CONNECTION
            || $connection->getDatabaseName() !== self::DATABASE) {
            throw new MySql24eGuardException('The dedicated Sale worker connection is not exact.');
        }
    }

    private function checkoutToken(): string
    {
        return Str::uuid()->toString();
    }

    /** @return array<string, int> */
    private function domainCounts(Connection $connection): array
    {
        $counts = [];
        foreach (self::DOMAIN_TABLES as $table) {
            $counts[$table] = $connection->table($table)->count();
        }

        return $counts;
    }

    private function activeRegisterCount(Connection $connection): int
    {
        return $connection->table('cash_register_sessions')->where('active_slot', 1)->count();
    }

    /** @param array<string, mixed> $fixture */
    private function assertActiveSession(Connection $connection, array $fixture): void
    {
        $session = $connection->table('cash_register_sessions')->where('id', $fixture['session_id'])->first();
        $this->assertNotNull($session);
        $this->assertSame(1, (int) $session->active_slot);
        $this->assertNull($session->closed_by);
        $this->assertNull($session->closed_at);
        $this->assertSame(1, $this->activeRegisterCount($connection));
    }

    private function variantStock(Connection $connection, int $variantId): string
    {
        return (string) $connection->table('product_variants')->where('id', $variantId)->value('current_stock');
    }

    private function assertInitialStockCount(Connection $connection, int $variantId): void
    {
        $this->assertSame(
            1,
            $connection->table('stock_movements')
                ->where('product_variant_id', $variantId)
                ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
                ->count(),
        );
    }

    /** @return list<int> */
    private function saleItemIds(Connection $connection, int ...$saleIds): array
    {
        return $connection->table('sale_items')
            ->whereIn('sale_id', $saleIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $itemIds @return list<int> */
    private function saleMovementIds(Connection $connection, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return $connection->table('stock_movements')
            ->whereIn('sale_item_id', $itemIds)
            ->where('movement_type', StockMovement::TYPE_SALE)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return list<object> */
    private function saleMovementsForVariant(Connection $connection, int $variantId): array
    {
        return $connection->table('stock_movements')
            ->where('product_variant_id', $variantId)
            ->where('movement_type', StockMovement::TYPE_SALE)
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function assertMovement(object $movement, string $before, string $change, string $after): void
    {
        $this->assertSame($before, (string) $movement->quantity_before);
        $this->assertSame($change, (string) $movement->quantity_change);
        $this->assertSame($after, (string) $movement->quantity_after);
    }

    /**
     * @param  list<array{socket: mixed, pid: int, joined: bool}>  $workers
     * @param  array<string, mixed>|null  $fixture
     * @param  list<string>  $tokens
     * @param  array<string, int>|null  $baseline
     */
    private function finishScenario(
        array &$workers,
        ?Connection $parentConnection,
        ?array $fixture,
        array $tokens,
        ?array $baseline,
        bool $parentTransactionActive,
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
            throw $firstFailure ?? new RuntimeException('The Sale scenario could not reach a safe cleanup boundary.');
        }

        if ($fixture !== null) {
            $this->cleanupFixture($fixture, $tokens);
            if ($baseline !== null) {
                $this->assertSame($baseline, $this->domainCounts($this->guardedConcurrencyConnection()));
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
            $saleIds = $connection->table('sales')
                ->whereIn('checkout_token', $tokens)
                ->whereIn('recorded_by', $fixture['user_ids'])
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $itemIds = $saleIds === []
                ? []
                : $connection->table('sale_items')
                    ->whereIn('sale_id', $saleIds)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

            if ($itemIds !== []) {
                $connection->table('stock_movements')
                    ->whereIn('sale_item_id', $itemIds)
                    ->whereIn('product_variant_id', $fixture['variant_ids'])
                    ->where('movement_type', StockMovement::TYPE_SALE)
                    ->delete();
            }
            if ($saleIds !== []) {
                $connection->table('sale_items')->whereIn('sale_id', $saleIds)->delete();
                $connection->table('sales')->whereIn('id', $saleIds)->delete();
            }
            $connection->table('cash_register_sessions')->whereIn('id', $fixture['session_ids'])->delete();
            $connection->table('audit_logs')->whereIn('user_id', $fixture['user_ids'])->delete();
            $connection->table('stock_movements')
                ->whereIn('product_variant_id', $fixture['variant_ids'])
                ->delete();
            $connection->table('product_variants')->whereIn('id', $fixture['variant_ids'])->delete();
            $connection->table('products')->whereIn('id', $fixture['product_ids'])->delete();
            $connection->table('categories')->whereIn('id', $fixture['category_ids'])->delete();
            $connection->table('users')->whereIn('id', $fixture['user_ids'])->delete();
        });
    }
}

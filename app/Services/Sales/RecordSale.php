<?php

namespace App\Services\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class RecordSale
{
    private const TOKEN_PATTERN = '/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D';

    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const MONEY_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_QUANTITY = '99999999999.999';

    private const MAX_MONEY = '99999999999999.99';

    private const MAX_UNIT_PRICE = '9999999999.99';

    public function execute(
        User $actor,
        mixed $submissionToken,
        mixed $amountTendered,
        mixed $submittedItems,
    ): Sale {
        $operation = $this->canonicalizeOperation($actor, $submissionToken, $amountTendered, $submittedItems);

        $existing = Sale::query()
            ->where('checkout_token', $operation['checkout_token'])
            ->with(['items' => fn ($query) => $query->orderBy('product_variant_id')->with('saleMovement')])
            ->first();

        if ($existing !== null) {
            return $this->resolveReplay($existing, $operation);
        }

        try {
            return DB::transaction(fn (): Sale => $this->record($operation));
        } catch (QueryException $exception) {
            if (! $this->isCheckoutTokenDuplicate($exception)) {
                throw $exception;
            }

            return DB::transaction(fn (): Sale => $this->recoverCollision($operation));
        }
    }

    /** @param array<string, mixed> $operation */
    private function record(array $operation): Sale
    {
        /** @var list<int> $variantIds */
        $variantIds = array_keys($operation['items']);
        $variantMappings = ProductVariant::query()
            ->whereKey($variantIds)
            ->get(['id', 'product_id'])
            ->keyBy('id');
        if ($variantMappings->count() !== count($variantIds)) {
            throw ValidationException::withMessages(['items' => 'One or more selected items no longer exist.']);
        }

        $productIds = $variantMappings->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $productMappings = Product::query()->whereKey($productIds)->get(['id', 'category_id'])->keyBy('id');
        if ($productMappings->count() !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Review the cart and try again.']);
        }

        $categoryIds = $productMappings->pluck('category_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $categories = Category::query()
            ->whereKey($categoryIds)->orderBy('id')->lockForUpdate()
            ->get(['id', 'status'])->keyBy('id');
        $products = Product::query()
            ->whereKey($productIds)->orderBy('id')->lockForUpdate()
            ->get(['id', 'category_id', 'name', 'status'])->keyBy('id');
        $variants = ProductVariant::query()
            ->whereKey($variantIds)->orderBy('id')->lockForUpdate()
            ->get([
                'id', 'product_id', 'size', 'type_series', 'thickness', 'unit', 'quantity_mode',
                'current_stock', 'selling_price', 'status',
            ])->keyBy('id');

        if ($categories->count() !== count($categoryIds)
            || $products->count() !== count($productIds)
            || $variants->count() !== count($variantIds)) {
            throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Review the cart and try again.']);
        }

        foreach ($variants as $variant) {
            $mapping = $variantMappings->get($variant->getKey());
            $product = $products->get((int) $variant->product_id);
            $productMapping = $productMappings->get((int) $variant->product_id);
            $category = $product === null ? null : $categories->get((int) $product->category_id);
            if ($mapping === null || $product === null || $productMapping === null || $category === null
                || (int) $variant->product_id !== (int) $mapping->product_id
                || (int) $product->category_id !== (int) $productMapping->category_id) {
                throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Review the cart and try again.']);
            }
            if ($category->status !== Category::STATUS_ACTIVE
                || $product->status !== Product::STATUS_ACTIVE
                || $variant->status !== ProductVariant::STATUS_ACTIVE) {
                $index = $operation['items'][$variant->id]['index'];
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Sales require an active category, product, and variant.',
                ]);
            }
        }

        $initializedIds = StockMovement::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        foreach ($variantIds as $variantId) {
            if (! in_array($variantId, $initializedIds, true)) {
                $index = $operation['items'][$variantId]['index'];
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Opening inventory must be completed before this item can be sold.',
                ]);
            }
        }

        $total = '0.00';
        foreach ($variants as $variant) {
            $item = &$operation['items'][$variant->id];
            foreach ($item['components'] as $component) {
                $this->validateQuantityMode($component['quantity'], (string) $variant->quantity_mode, $component['index']);
            }

            $authoritativePrice = $this->canonicalPersistedPositiveMoney((string) $variant->selling_price, 'The item has an invalid stored selling price.');
            if (bccomp($item['expected_unit_price'], $authoritativePrice, 2) !== 0) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.expected_unit_price" => 'The price for this item changed. Review the updated price and try again.',
                ]);
            }
            $raw = bcmul($item['quantity'], $authoritativePrice, 5);
            $lineTotal = bcadd($raw, '0.005', 2);
            if (bccomp($lineTotal, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.quantity" => 'The quantity is too small to produce a billable amount at the current price.',
                ]);
            }
            if (bccomp($lineTotal, self::MAX_MONEY, 2) === 1) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.quantity" => 'The item total is too large.',
                ]);
            }
            $total = bcadd($total, $lineTotal, 2);
            if (bccomp($total, self::MAX_MONEY, 2) === 1) {
                throw ValidationException::withMessages(['items' => 'The sale total is too large.']);
            }
            $item['unit_price'] = $authoritativePrice;
            $item['line_total'] = $lineTotal;
            unset($item);
        }

        if (bccomp($operation['cash_received'], $total, 2) === -1) {
            throw ValidationException::withMessages(['amount_tendered' => 'The tendered cash is less than the sale total.']);
        }
        $change = bcsub($operation['cash_received'], $total, 2);

        $sale = new Sale;
        $sale->checkout_token = $operation['checkout_token'];
        $sale->recorded_by = $operation['actor_id'];
        $sale->status = Sale::STATUS_COMPLETED;
        $sale->total_amount = $total;
        $sale->cash_received = $operation['cash_received'];
        $sale->change_amount = $change;
        $sale->void_reason = null;
        $sale->voided_by = null;
        $sale->voided_at = null;
        $sale->save();

        foreach ($variants as $variant) {
            $item = $operation['items'][$variant->id];
            if (bccomp($item['quantity'], (string) $variant->current_stock, 3) === 1) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.quantity" => "Insufficient stock. Current availability is {$variant->current_stock} {$variant->unit}.",
                ]);
            }
        }

        $createdItems = collect();
        foreach ($variants as $variant) {
            $itemData = $operation['items'][$variant->id];
            $product = $products->get((int) $variant->product_id);
            $before = (string) $variant->current_stock;
            $after = bcsub($before, $itemData['quantity'], 3);

            $saleItem = new SaleItem;
            $saleItem->sale_id = $sale->getKey();
            $saleItem->product_variant_id = $variant->getKey();
            $saleItem->product_name_snapshot = $product->name;
            $saleItem->size_snapshot = $variant->size;
            $saleItem->type_series_snapshot = $variant->type_series;
            $saleItem->thickness_snapshot = $variant->thickness;
            $saleItem->unit_snapshot = $variant->unit;
            $saleItem->quantity = $itemData['quantity'];
            $saleItem->unit_price = $itemData['unit_price'];
            $saleItem->line_total = $itemData['line_total'];
            $saleItem->save();

            $variant->current_stock = $after;
            $variant->save();

            $movement = new StockMovement;
            $movement->product_variant_id = $variant->getKey();
            $movement->movement_type = StockMovement::TYPE_SALE;
            $movement->quantity_before = $before;
            $movement->quantity_change = bcsub('0.000', $itemData['quantity'], 3);
            $movement->quantity_after = $after;
            $movement->performed_by = $operation['actor_id'];
            $movement->sale_item_id = $saleItem->getKey();
            $movement->restock_item_id = null;
            $movement->reason = null;
            $movement->save();

            $saleItem->setRelation('saleMovement', $movement);
            $createdItems->push($saleItem);
        }
        $sale->setRelation('items', $createdItems);

        return $sale;
    }

    /** @return array<string, mixed> */
    private function canonicalizeOperation(User $actor, mixed $submissionToken, mixed $amountTendered, mixed $submittedItems): array
    {
        if (! is_array($submittedItems) || count($submittedItems) < 1 || count($submittedItems) > 100) {
            throw ValidationException::withMessages(['items' => 'A sale requires between 1 and 100 item rows.']);
        }
        if (! is_string($submissionToken)) {
            throw ValidationException::withMessages(['submission_token' => 'The checkout token is invalid.']);
        }
        $token = trim($submissionToken);
        if (strlen($token) !== 36 || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw ValidationException::withMessages(['submission_token' => 'The checkout token is invalid.']);
        }
        $token = strtolower($token);
        $cash = $this->canonicalMoney($amountTendered, 'amount_tendered', false, self::MAX_MONEY, 'Enter a nonnegative cash amount with up to two decimal places.');

        $items = [];
        foreach (array_values($submittedItems) as $index => $submitted) {
            if (! is_array($submitted)) {
                throw ValidationException::withMessages(["items.{$index}" => 'Each sale item must be an object.']);
            }
            $keys = array_keys($submitted);
            sort($keys);
            if ($keys !== ['expected_unit_price', 'product_variant_id', 'quantity']) {
                throw ValidationException::withMessages(["items.{$index}" => 'The sale item contains unexpected fields.']);
            }
            $variantId = filter_var($submitted['product_variant_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($variantId === false) {
                throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'Select a valid product variant.']);
            }
            $quantity = $this->canonicalQuantity($submitted['quantity'], $index);
            $price = $this->canonicalMoney(
                $submitted['expected_unit_price'], "items.{$index}.expected_unit_price", true,
                self::MAX_UNIT_PRICE, 'Enter a positive expected price with up to two decimal places.',
            );

            if (isset($items[$variantId])) {
                if (bccomp($items[$variantId]['expected_unit_price'], $price, 2) !== 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.expected_unit_price" => 'The same item was submitted with different expected prices. Review the cart and try again.',
                    ]);
                }
                $aggregate = bcadd($items[$variantId]['quantity'], $quantity, 3);
                if (bccomp($aggregate, self::MAX_QUANTITY, 3) === 1) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'The combined quantity is too large.']);
                }
                $items[$variantId]['quantity'] = $aggregate;
                $items[$variantId]['components'][] = ['index' => $index, 'quantity' => $quantity];
            } else {
                $items[(int) $variantId] = [
                    'index' => $index,
                    'product_variant_id' => (int) $variantId,
                    'quantity' => $quantity,
                    'expected_unit_price' => $price,
                    'components' => [['index' => $index, 'quantity' => $quantity]],
                ];
            }
        }
        ksort($items, SORT_NUMERIC);

        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actorId === false) {
            throw ValidationException::withMessages(['actor' => 'A persisted active cashier is required.']);
        }
        $persistedActor = User::query()->find((int) $actorId, ['id', 'role', 'status']);
        if ($persistedActor === null
            || $persistedActor->status !== User::STATUS_ACTIVE
            || ! in_array($persistedActor->role, [User::ROLE_ADMIN, 'staff'], true)) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin or Staff cashier is required.']);
        }

        return [
            'checkout_token' => $token,
            'cash_received' => $cash,
            'actor_id' => (int) $persistedActor->getKey(),
            'items' => $items,
        ];
    }

    private function canonicalQuantity(mixed $value, int $index): string
    {
        if (! is_string($value) || preg_match(self::QUANTITY_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'Enter a positive ordinary decimal with up to three decimal places.']);
        }
        $canonical = $this->canonicalUnsigned(trim($value), 3);
        if (strlen(strtok($canonical, '.')) > 11 || bccomp($canonical, self::MAX_QUANTITY, 3) === 1) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The quantity is too large.']);
        }
        if (bccomp($canonical, '0.000', 3) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The quantity must be greater than zero.']);
        }

        return $canonical;
    }

    private function canonicalMoney(mixed $value, string $field, bool $positive, string $maximum, string $syntaxMessage): string
    {
        if (! is_string($value) || preg_match(self::MONEY_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages([$field => $syntaxMessage]);
        }
        $canonical = $this->canonicalUnsigned(trim($value), 2);
        if (strlen(strtok($canonical, '.')) > strlen(strtok($maximum, '.')) || bccomp($canonical, $maximum, 2) === 1) {
            throw ValidationException::withMessages([$field => 'This monetary amount is too large.']);
        }
        if ($positive && bccomp($canonical, '0.00', 2) !== 1) {
            throw ValidationException::withMessages([$field => 'The expected price must be greater than zero.']);
        }

        return $canonical;
    }

    private function canonicalUnsigned(string $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, $scale, '0');
    }

    private function canonicalPersistedPositiveMoney(string $value, string $message): string
    {
        if (preg_match(self::MONEY_PATTERN, $value) !== 1) {
            throw new LogicException($message);
        }
        $canonical = $this->canonicalUnsigned($value, 2);
        if (bccomp($canonical, '0.00', 2) !== 1 || bccomp($canonical, self::MAX_UNIT_PRICE, 2) === 1) {
            throw new LogicException($message);
        }

        return $canonical;
    }

    private function validateQuantityMode(string $quantity, string $quantityMode, int $index): void
    {
        if (! in_array($quantityMode, ProductVariant::QUANTITY_MODES, true)) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The item quantity mode is invalid.']);
        }
        if ($quantityMode === 'whole' && ! str_ends_with($quantity, '.000')) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The quantity must be a whole number for this item.']);
        }
    }

    /** @param array<string, mixed> $operation */
    private function recoverCollision(array $operation): Sale
    {
        $sale = Sale::query()
            ->where('checkout_token', $operation['checkout_token'])
            ->lockForUpdate()
            ->first();
        if ($sale === null) {
            throw new LogicException('The committed checkout could not be resolved.');
        }
        $items = SaleItem::query()
            ->where('sale_id', $sale->getKey())
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();
        $movements = StockMovement::query()
            ->whereIn('sale_item_id', $items->modelKeys())
            ->where('movement_type', StockMovement::TYPE_SALE)
            ->orderBy('sale_item_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('sale_item_id');
        foreach ($items as $item) {
            $item->setRelation('saleMovement', $movements->get($item->getKey()));
        }
        $sale->setRelation('items', $items);

        return $this->resolveReplay($sale, $operation);
    }

    /** @param array<string, mixed> $operation */
    private function resolveReplay(Sale $sale, array $operation): Sale
    {
        $this->verifyHistoricalEvidence($sale);
        $stored = $sale->items->sortBy('product_variant_id')->values();
        $requested = collect($operation['items'])->values();
        $equivalent = (int) $sale->recorded_by === $operation['actor_id']
            && bccomp((string) $sale->cash_received, $operation['cash_received'], 2) === 0
            && $stored->count() === $requested->count();

        if ($equivalent) {
            foreach ($stored as $offset => $item) {
                $requestItem = $requested[$offset];
                if ((int) $item->product_variant_id !== $requestItem['product_variant_id']
                    || bccomp((string) $item->quantity, $requestItem['quantity'], 3) !== 0
                    || bccomp((string) $item->unit_price, $requestItem['expected_unit_price'], 2) !== 0) {
                    $equivalent = false;
                    break;
                }
            }
        }
        if (! $equivalent) {
            throw ValidationException::withMessages([
                'submission_token' => 'This checkout token is already associated with a different sale. Review the cart and try again.',
            ]);
        }

        return $sale;
    }

    private function verifyHistoricalEvidence(Sale $sale): void
    {
        if ($sale->status !== Sale::STATUS_COMPLETED
            || $sale->void_reason !== null || $sale->voided_by !== null || $sale->voided_at !== null
            || $sale->items->isEmpty()) {
            throw new LogicException('The stored sale evidence is internally inconsistent.');
        }

        $total = '0.00';
        $seenVariants = [];
        foreach ($sale->items as $item) {
            $quantity = (string) $item->quantity;
            $unitPrice = (string) $item->unit_price;
            $lineTotal = (string) $item->line_total;
            if (isset($seenVariants[(int) $item->product_variant_id])
                || bccomp($quantity, '0.000', 3) !== 1
                || bccomp($unitPrice, '0.00', 2) !== 1
                || bccomp($lineTotal, '0.00', 2) !== 1
                || bccomp($lineTotal, bcadd(bcmul($quantity, $unitPrice, 5), '0.005', 2), 2) !== 0) {
                throw new LogicException('The stored sale evidence is internally inconsistent.');
            }
            $seenVariants[(int) $item->product_variant_id] = true;
            $total = bcadd($total, $lineTotal, 2);

            $movement = $item->saleMovement;
            $expectedChange = bcsub('0.000', $quantity, 3);
            if (! $movement instanceof StockMovement
                || $movement->movement_type !== StockMovement::TYPE_SALE
                || (int) $movement->product_variant_id !== (int) $item->product_variant_id
                || (int) $movement->sale_item_id !== (int) $item->getKey()
                || (int) $movement->performed_by !== (int) $sale->recorded_by
                || $movement->restock_item_id !== null || $movement->reason !== null
                || bccomp((string) $movement->quantity_before, '0.000', 3) === -1
                || bccomp((string) $movement->quantity_after, '0.000', 3) === -1
                || bccomp((string) $movement->quantity_change, $expectedChange, 3) !== 0
                || bccomp(
                    bcadd((string) $movement->quantity_before, (string) $movement->quantity_change, 3),
                    (string) $movement->quantity_after,
                    3,
                ) !== 0) {
                throw new LogicException('The stored SALE movement evidence is internally inconsistent.');
            }
        }

        if (bccomp($total, '0.00', 2) !== 1
            || bccomp((string) $sale->total_amount, $total, 2) !== 0
            || bccomp((string) $sale->cash_received, $total, 2) === -1
            || bccomp(
                (string) $sale->change_amount,
                bcsub((string) $sale->cash_received, $total, 2),
                2,
            ) !== 0) {
            throw new LogicException('The stored sale payment evidence is internally inconsistent.');
        }
    }

    private function isCheckoutTokenDuplicate(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return ($sqlState === '23000' && $driverCode === 1062
                && str_contains($message, 'sales_checkout_token_unique'))
            || ($driverCode === 19
                && str_contains($message, 'unique constraint failed: sales.checkout_token'));
    }
}

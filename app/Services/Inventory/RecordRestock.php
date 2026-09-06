<?php

namespace App\Services\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restock;
use App\Models\RestockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Throwable;

class RecordRestock
{
    private const TOKEN_PATTERN = '/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D';

    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_QUANTITY = '99999999999.999';

    private const MAX_MONEY = '99999999999999.99';

    /**
     * @param  list<array<string, mixed>>  $submittedItems
     */
    public function execute(
        User $actor,
        string $submissionToken,
        mixed $referenceText,
        mixed $notes,
        array $submittedItems,
    ): Restock {
        $operation = $this->canonicalizeOperation(
            $actor,
            $submissionToken,
            $referenceText,
            $notes,
            $submittedItems,
        );

        $existing = Restock::query()
            ->where('submission_token', $operation['submission_token'])
            ->with(['items' => fn ($query) => $query->orderBy('product_variant_id')])
            ->first();

        if ($existing !== null) {
            return $this->resolveReplay($existing, $operation);
        }

        try {
            return DB::transaction(fn (): Restock => $this->record($operation));
        } catch (SubmissionTokenCollision $collision) {
            $winner = Restock::query()
                ->where('submission_token', $operation['submission_token'])
                ->lockForUpdate()
                ->first();

            if ($winner === null) {
                throw new LogicException('The committed Stock In submission could not be resolved.');
            }

            $items = RestockItem::query()
                ->where('restock_id', $winner->getKey())
                ->orderBy('product_variant_id')
                ->lockForUpdate()
                ->get();
            $winner->setRelation('items', $items);

            return $this->resolveReplay($winner, $operation);
        }
    }

    /** @param array<string, mixed> $operation */
    private function record(array $operation): Restock
    {
        $restock = new Restock;
        $restock->submission_token = $operation['submission_token'];
        $restock->recorded_by = $operation['actor_id'];
        $restock->reference_text = $operation['reference_text'];
        $restock->notes = $operation['notes'];
        $restock->total_cost = $operation['total_cost'];

        try {
            $restock->save();
        } catch (QueryException $exception) {
            if (! $this->isSubmissionTokenDuplicate($exception)) {
                throw $exception;
            }

            throw new SubmissionTokenCollision($exception);
        }

        /** @var list<int> $variantIds */
        $variantIds = array_keys($operation['items']);
        $variantSnapshots = ProductVariant::query()
            ->whereKey($variantIds)
            ->get(['id', 'product_id'])
            ->keyBy('id');
        if ($variantSnapshots->count() !== count($variantIds)) {
            throw ValidationException::withMessages(['items' => 'One or more selected variants no longer exist.']);
        }

        $productIds = $variantSnapshots->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $productSnapshots = Product::query()->whereKey($productIds)->get(['id', 'category_id'])->keyBy('id');
        if ($productSnapshots->count() !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Please retry.']);
        }

        $categoryIds = $productSnapshots->pluck('category_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $categories = Category::query()->whereKey($categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $products = Product::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $variants = ProductVariant::query()->whereKey($variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if ($categories->count() !== count($categoryIds)
            || $products->count() !== count($productIds)
            || $variants->count() !== count($variantIds)) {
            throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Please retry.']);
        }

        foreach ($variants as $variant) {
            $snapshot = $variantSnapshots->get($variant->getKey());
            $product = $products->get((int) $variant->product_id);
            $productSnapshot = $productSnapshots->get((int) $variant->product_id);
            $category = $product === null ? null : $categories->get((int) $product->category_id);

            if ($snapshot === null || $product === null || $productSnapshot === null || $category === null
                || (int) $variant->product_id !== (int) $snapshot->product_id
                || (int) $product->category_id !== (int) $productSnapshot->category_id) {
                throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Please retry.']);
            }

            if ($category->status !== Category::STATUS_ACTIVE
                || $product->status !== Product::STATUS_ACTIVE
                || $variant->status !== ProductVariant::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    "items.{$operation['items'][$variant->id]['index']}.product_variant_id" => 'Stock In requires an active category, product, and variant.',
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
                    "items.{$index}.product_variant_id" => 'Opening inventory must be completed before Stock In.',
                ]);
            }
        }

        $createdItems = collect();
        foreach ($variants as $variant) {
            $itemData = $operation['items'][$variant->id];
            $index = $itemData['index'];
            $this->validateQuantityMode($itemData['quantity'], $variant->quantity_mode, $index);

            $before = (string) $variant->current_stock;
            $after = bcadd($before, $itemData['quantity'], 3);
            if (bccomp($after, self::MAX_QUANTITY, 3) === 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'This receipt would exceed the maximum stock quantity.',
                ]);
            }

            $product = $products->get((int) $variant->product_id);
            $item = new RestockItem;
            $item->restock_id = $restock->getKey();
            $item->product_variant_id = $variant->getKey();
            $item->product_name_snapshot = $product->name;
            $item->size_snapshot = $variant->size;
            $item->type_series_snapshot = $variant->type_series;
            $item->thickness_snapshot = $variant->thickness;
            $item->unit_snapshot = $variant->unit;
            $item->quantity = $itemData['quantity'];
            $item->unit_cost = $itemData['unit_cost'];
            $item->line_total = $itemData['line_total'];
            $item->save();

            $variant->current_stock = $after;
            $variant->cost_price = $itemData['unit_cost'];
            $variant->save();

            $movement = new StockMovement;
            $movement->product_variant_id = $variant->getKey();
            $movement->movement_type = StockMovement::TYPE_RESTOCK;
            $movement->quantity_before = $before;
            $movement->quantity_change = $itemData['quantity'];
            $movement->quantity_after = $after;
            $movement->performed_by = $operation['actor_id'];
            $movement->sale_item_id = null;
            $movement->restock_item_id = $item->getKey();
            $movement->reason = null;
            $movement->save();
            $item->setRelation('stockMovement', $movement);
            $createdItems->push($item);
        }

        $restock->setRelation('items', $createdItems);

        return $restock;
    }

    /**
     * @param  list<array<string, mixed>>  $submittedItems
     * @return array<string, mixed>
     */
    private function canonicalizeOperation(
        User $actor,
        string $submissionToken,
        mixed $referenceText,
        mixed $notes,
        array $submittedItems,
    ): array {
        $token = trim($submissionToken);
        if (strlen($token) !== 36 || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw ValidationException::withMessages(['submission_token' => 'The Stock In submission token is invalid.']);
        }
        $token = strtolower($token);

        if (count($submittedItems) < 1 || count($submittedItems) > 100) {
            throw ValidationException::withMessages(['items' => 'Stock In requires between 1 and 100 items.']);
        }

        $items = [];
        $total = '0.00';
        foreach (array_values($submittedItems) as $index => $submitted) {
            if (! is_array($submitted)) {
                throw ValidationException::withMessages(["items.{$index}" => 'Each Stock In item must be an object.']);
            }

            $keys = array_keys($submitted);
            sort($keys);
            if ($keys !== ['product_variant_id', 'quantity', 'unit_cost']) {
                throw ValidationException::withMessages(["items.{$index}" => 'The Stock In item contains unexpected fields.']);
            }

            $variantId = filter_var($submitted['product_variant_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($variantId === false) {
                throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'Select a valid product variant.']);
            }
            if (isset($items[$variantId])) {
                throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'A variant may appear only once in a Stock In receipt.']);
            }

            $quantity = $this->canonicalQuantity($submitted['quantity'], $index);
            $unitCost = $this->canonicalCost($submitted['unit_cost'], $index);
            $lineTotal = bcadd(bcmul($quantity, $unitCost, 5), '0.005', 2);
            if (bccomp($lineTotal, self::MAX_MONEY, 2) === 1) {
                throw ValidationException::withMessages(["items.{$index}.unit_cost" => 'The item total is too large.']);
            }

            $total = bcadd($total, $lineTotal, 2);
            if (bccomp($total, self::MAX_MONEY, 2) === 1) {
                throw ValidationException::withMessages(['items' => 'The Stock In total is too large.']);
            }

            $items[(int) $variantId] = [
                'index' => $index,
                'product_variant_id' => (int) $variantId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }
        ksort($items, SORT_NUMERIC);

        return [
            'submission_token' => $token,
            'actor_id' => (int) $actor->getKey(),
            'reference_text' => $this->normalizeHeaderText($referenceText, 'reference_text'),
            'notes' => $this->normalizeHeaderText($notes, 'notes'),
            'total_cost' => $total,
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
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The received quantity is too large.']);
        }
        if (bccomp($canonical, '0.000', 3) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The received quantity must be greater than zero.']);
        }

        return $canonical;
    }

    private function canonicalCost(mixed $value, int $index): string
    {
        if (! is_string($value) || preg_match(self::COST_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.unit_cost" => 'Enter a nonnegative ordinary decimal with up to two decimal places.']);
        }

        $canonical = $this->canonicalUnsigned(trim($value), 2);
        if (strlen(strtok($canonical, '.')) > 10 || bccomp($canonical, '9999999999.99', 2) === 1) {
            throw ValidationException::withMessages(["items.{$index}.unit_cost" => 'The unit cost is too large.']);
        }

        return $canonical;
    }

    private function canonicalUnsigned(string $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, $scale, '0');
    }

    private function normalizeHeaderText(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'This field must be text.']);
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        if (! is_string($normalized)) {
            throw ValidationException::withMessages([$field => 'This field contains invalid text.']);
        }
        if ($normalized === '') {
            return null;
        }
        if (mb_strlen($normalized) > 1000) {
            throw ValidationException::withMessages([$field => 'This field must not exceed 1000 characters.']);
        }

        return $normalized;
    }

    private function validateQuantityMode(string $quantity, string $quantityMode, int $index): void
    {
        if (! in_array($quantityMode, ProductVariant::QUANTITY_MODES, true)) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The variant quantity mode is invalid.']);
        }
        if ($quantityMode === 'whole' && ! str_ends_with($quantity, '.000')) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'The received quantity must be a whole number for this variant.']);
        }
    }

    /** @param array<string, mixed> $operation */
    private function resolveReplay(Restock $restock, array $operation): Restock
    {
        $persistedItems = $restock->items
            ->sortBy('product_variant_id')
            ->values()
            ->map(fn (RestockItem $item): array => [
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
                'line_total' => (string) $item->line_total,
            ])->all();
        $requestedItems = collect($operation['items'])->values()->map(fn (array $item): array => [
            'product_variant_id' => $item['product_variant_id'],
            'quantity' => $item['quantity'],
            'unit_cost' => $item['unit_cost'],
            'line_total' => $item['line_total'],
        ])->all();

        if ((int) $restock->recorded_by !== $operation['actor_id']
            || $restock->reference_text !== $operation['reference_text']
            || $restock->notes !== $operation['notes']
            || (string) $restock->total_cost !== $operation['total_cost']
            || $persistedItems !== $requestedItems) {
            throw ValidationException::withMessages([
                'submission_token' => 'This Stock In submission token cannot be reused.',
            ]);
        }

        return $restock;
    }

    private function isSubmissionTokenDuplicate(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return ($sqlState === '23000' && $driverCode === 1062
                && str_contains($message, 'restocks_submission_token_unique'))
            || ($driverCode === 19
                && str_contains($message, 'unique constraint failed: restocks.submission_token'));
    }
}

final class SubmissionTokenCollision extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('A Stock In submission-token collision occurred.', previous: $previous);
    }
}

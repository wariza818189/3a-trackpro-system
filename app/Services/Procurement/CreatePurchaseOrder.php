<?php

namespace App\Services\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Throwable;

class CreatePurchaseOrder
{
    private const TOKEN_PATTERN = '/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D';

    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_QUANTITY = '99999999999.999';

    private const MAX_COST = '9999999999.99';

    public function execute(
        User $actor,
        mixed $submissionToken,
        mixed $supplierName,
        mixed $notes,
        mixed $submittedItems,
    ): PurchaseOrder {
        $operation = $this->canonicalizeOperation(
            $actor,
            $submissionToken,
            $supplierName,
            $notes,
            $submittedItems,
        );

        $existing = DB::transaction(function () use ($operation): ?PurchaseOrder {
            $purchaseOrder = $this->findByTokenForUpdate($operation['submission_token']);

            return $purchaseOrder === null ? null : $this->resolveReplay($purchaseOrder, $operation);
        });
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): PurchaseOrder => $this->create($operation));
        } catch (PurchaseOrderSubmissionTokenCollision) {
            return DB::transaction(function () use ($operation): PurchaseOrder {
                $winner = $this->findByTokenForUpdate($operation['submission_token']);
                if ($winner === null) {
                    throw new LogicException('The committed Purchase Order submission could not be resolved.');
                }

                return $this->resolveReplay($winner, $operation);
            });
        }
    }

    /** @param array<string, mixed> $operation */
    private function create(array $operation): PurchaseOrder
    {
        $actor = User::query()
            ->whereKey($operation['actor_id'])
            ->lockForUpdate()
            ->first(['id', 'role', 'status']);
        if ($actor === null || ! $actor->isActive() || ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }

        /** @var list<int> $variantIds */
        $variantIds = array_keys($operation['items']);
        $variantMappings = ProductVariant::query()
            ->whereKey($variantIds)
            ->get(['id', 'product_id'])
            ->keyBy('id');
        if ($variantMappings->count() !== count($variantIds)) {
            throw ValidationException::withMessages(['items' => 'One or more selected variants no longer exist.']);
        }

        $productIds = $variantMappings->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $productMappings = Product::query()
            ->whereKey($productIds)
            ->get(['id', 'category_id'])
            ->keyBy('id');
        if ($productMappings->count() !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Please retry.']);
        }

        $categoryIds = $productMappings->pluck('category_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $categories = Category::query()
            ->whereKey($categoryIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'status'])
            ->keyBy('id');
        $products = Product::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'category_id', 'name', 'status'])
            ->keyBy('id');
        $variants = ProductVariant::query()
            ->whereKey($variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get([
                'id', 'product_id', 'size', 'type_series', 'thickness', 'unit',
                'quantity_mode', 'status',
            ])
            ->keyBy('id');

        if ($categories->count() !== count($categoryIds)
            || $products->count() !== count($productIds)
            || $variants->count() !== count($variantIds)) {
            throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Please retry.']);
        }

        foreach ($variantIds as $variantId) {
            $variant = $variants->get($variantId);
            $variantMapping = $variantMappings->get($variantId);
            $product = $variant === null ? null : $products->get((int) $variant->product_id);
            $productMapping = $product === null ? null : $productMappings->get((int) $product->getKey());
            $category = $product === null ? null : $categories->get((int) $product->category_id);
            $index = $operation['items'][$variantId]['index'];

            if ($variant === null || $variantMapping === null || $product === null
                || $productMapping === null || $category === null
                || (int) $variant->product_id !== (int) $variantMapping->product_id
                || (int) $product->category_id !== (int) $productMapping->category_id) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'The catalog hierarchy changed. Please retry.',
                ]);
            }

            if ($category->status !== Category::STATUS_ACTIVE
                || $product->status !== Product::STATUS_ACTIVE
                || $variant->status !== ProductVariant::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Purchase Orders require an active category, product, and variant.',
                ]);
            }
        }

        // The explicit hierarchy and movement queries preserve the project-wide
        // lock order more clearly than composing the read-oriented catalog builder.
        // Their predicate remains identical: active hierarchy plus INITIAL_STOCK.
        $initializedIds = StockMovement::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();
        foreach ($variantIds as $variantId) {
            if (! in_array($variantId, $initializedIds, true)) {
                $index = $operation['items'][$variantId]['index'];
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Opening inventory must be completed before creating a Purchase Order.',
                ]);
            }
        }

        foreach ($variantIds as $variantId) {
            $this->validateQuantityMode(
                $operation['items'][$variantId]['ordered_quantity'],
                (string) $variants->get($variantId)->quantity_mode,
                $operation['items'][$variantId]['index'],
            );
        }

        $purchaseOrder = new PurchaseOrder;
        $purchaseOrder->parent_purchase_order_id = null;
        $purchaseOrder->submission_token = $operation['submission_token'];
        $purchaseOrder->created_by = $actor->getKey();
        $purchaseOrder->supplier_name = $operation['supplier_name'];
        $purchaseOrder->status = PurchaseOrder::STATUS_PENDING;
        $purchaseOrder->notes = $operation['notes'];

        try {
            $purchaseOrder->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isSubmissionTokenConstraint($exception)) {
                throw $exception;
            }

            throw new PurchaseOrderSubmissionTokenCollision($exception);
        }

        $createdItems = collect();
        foreach ($variantIds as $variantId) {
            $variant = $variants->get($variantId);
            $product = $products->get((int) $variant->product_id);
            $itemData = $operation['items'][$variantId];

            $item = new PurchaseOrderItem;
            $item->purchase_order_id = $purchaseOrder->getKey();
            $item->product_variant_id = $variant->getKey();
            $item->product_name_snapshot = $product->name;
            $item->size_snapshot = $variant->size;
            $item->type_series_snapshot = $variant->type_series;
            $item->thickness_snapshot = $variant->thickness;
            $item->unit_snapshot = $variant->unit;
            $item->ordered_quantity = $itemData['ordered_quantity'];
            $item->expected_unit_cost = $itemData['expected_unit_cost'];
            $item->save();
            $createdItems->push($item);
        }

        $purchaseOrder->setRelation('items', $createdItems);

        return $purchaseOrder;
    }

    /**
     * @return array{
     *     submission_token: string,
     *     actor_id: int,
     *     supplier_name: string,
     *     notes: string|null,
     *     parent_purchase_order_id: null,
     *     items: array<int, array{index: int, product_variant_id: int, ordered_quantity: string, expected_unit_cost: string}>
     * }
     */
    private function canonicalizeOperation(
        User $actor,
        mixed $submissionToken,
        mixed $supplierName,
        mixed $notes,
        mixed $submittedItems,
    ): array {
        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actorId === false) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }
        $persistedActor = User::query()->find((int) $actorId, ['id', 'role', 'status']);
        if ($persistedActor === null || ! $persistedActor->isActive() || ! $persistedActor->isAdmin()) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }

        if (! is_string($submissionToken)) {
            throw ValidationException::withMessages(['submission_token' => 'The Purchase Order submission token is invalid.']);
        }
        $token = trim($submissionToken);
        if (strlen($token) !== 36 || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw ValidationException::withMessages(['submission_token' => 'The Purchase Order submission token is invalid.']);
        }
        $token = strtolower($token);

        if (! is_array($submittedItems) || count($submittedItems) < 1 || count($submittedItems) > 100) {
            throw ValidationException::withMessages(['items' => 'A Purchase Order requires between 1 and 100 item rows.']);
        }

        $items = [];
        foreach (array_values($submittedItems) as $index => $submitted) {
            if (! is_array($submitted)) {
                throw ValidationException::withMessages(["items.{$index}" => 'Each Purchase Order item must be an object.']);
            }

            $keys = array_keys($submitted);
            sort($keys);
            if ($keys !== ['expected_unit_cost', 'ordered_quantity', 'product_variant_id']) {
                throw ValidationException::withMessages(["items.{$index}" => 'The Purchase Order item contains unexpected fields.']);
            }

            $variantId = filter_var($submitted['product_variant_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($variantId === false) {
                throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'Select a valid product variant.']);
            }
            if (isset($items[$variantId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'A variant may appear only once in a Purchase Order.',
                ]);
            }

            $items[(int) $variantId] = [
                'index' => $index,
                'product_variant_id' => (int) $variantId,
                'ordered_quantity' => $this->canonicalQuantity($submitted['ordered_quantity'], $index),
                'expected_unit_cost' => $this->canonicalCost($submitted['expected_unit_cost'], $index),
            ];
        }
        ksort($items, SORT_NUMERIC);

        return [
            'submission_token' => $token,
            'actor_id' => (int) $persistedActor->getKey(),
            'supplier_name' => $this->normalizeText($supplierName, 'supplier_name', 150, true),
            'notes' => $this->normalizeText($notes, 'notes', 1000, false),
            'parent_purchase_order_id' => null,
            'items' => $items,
        ];
    }

    private function canonicalQuantity(mixed $value, int $index): string
    {
        if (! is_string($value) || preg_match(self::QUANTITY_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages([
                "items.{$index}.ordered_quantity" => 'Enter a positive ordinary decimal with up to three decimal places.',
            ]);
        }

        $canonical = $this->canonicalUnsigned(trim($value), 3);
        if (strlen(strtok($canonical, '.')) > 11 || bccomp($canonical, self::MAX_QUANTITY, 3) === 1) {
            throw ValidationException::withMessages([
                "items.{$index}.ordered_quantity" => 'The ordered quantity is too large.',
            ]);
        }
        if (bccomp($canonical, '0.000', 3) !== 1) {
            throw ValidationException::withMessages([
                "items.{$index}.ordered_quantity" => 'The ordered quantity must be greater than zero.',
            ]);
        }

        return $canonical;
    }

    private function canonicalCost(mixed $value, int $index): string
    {
        if (! is_string($value) || preg_match(self::COST_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages([
                "items.{$index}.expected_unit_cost" => 'Enter a nonnegative ordinary decimal with up to two decimal places.',
            ]);
        }

        $canonical = $this->canonicalUnsigned(trim($value), 2);
        if (strlen(strtok($canonical, '.')) > 10 || bccomp($canonical, self::MAX_COST, 2) === 1) {
            throw ValidationException::withMessages([
                "items.{$index}.expected_unit_cost" => 'The expected unit cost is too large.',
            ]);
        }

        return $canonical;
    }

    private function canonicalUnsigned(string $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, $scale, '0');
    }

    private function normalizeText(mixed $value, string $field, int $maximum, bool $required): ?string
    {
        if ($value === null) {
            if ($required) {
                throw ValidationException::withMessages([$field => 'This field is required.']);
            }

            return null;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'This field must be text.']);
        }

        $normalized = preg_replace('/[\p{Z}\s]+/u', ' ', $value);
        if (! is_string($normalized)) {
            throw ValidationException::withMessages([$field => 'This field contains invalid text.']);
        }
        $normalized = trim($normalized);
        if ($normalized === '') {
            if ($required) {
                throw ValidationException::withMessages([$field => 'This field is required.']);
            }

            return null;
        }
        if (mb_strlen($normalized) > $maximum) {
            throw ValidationException::withMessages([$field => "This field must not exceed {$maximum} characters."]);
        }

        return $normalized;
    }

    private function validateQuantityMode(string $quantity, string $quantityMode, int $index): void
    {
        if (! in_array($quantityMode, ProductVariant::QUANTITY_MODES, true)) {
            throw ValidationException::withMessages([
                "items.{$index}.ordered_quantity" => 'The variant quantity mode is invalid.',
            ]);
        }
        if ($quantityMode === 'whole' && ! str_ends_with($quantity, '.000')) {
            throw ValidationException::withMessages([
                "items.{$index}.ordered_quantity" => 'The ordered quantity must be a whole number for this variant.',
            ]);
        }
    }

    private function findByTokenForUpdate(string $token): ?PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::query()
            ->where('submission_token', $token)
            ->lockForUpdate()
            ->first();
        if ($purchaseOrder === null) {
            return null;
        }

        $items = PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrder->getKey())
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();
        $purchaseOrder->setRelation('items', $items);

        return $purchaseOrder;
    }

    /** @param array<string, mixed> $operation */
    private function resolveReplay(PurchaseOrder $purchaseOrder, array $operation): PurchaseOrder
    {
        $storedItems = $purchaseOrder->items->sortBy('product_variant_id')->values();
        $requestedItems = collect($operation['items'])->values();
        $equivalent = (int) $purchaseOrder->created_by === $operation['actor_id']
            && $purchaseOrder->supplier_name === $operation['supplier_name']
            && $purchaseOrder->notes === $operation['notes']
            && $purchaseOrder->parent_purchase_order_id === null
            && $storedItems->count() === $requestedItems->count();

        if ($equivalent) {
            foreach ($storedItems as $offset => $storedItem) {
                $requestedItem = $requestedItems[$offset];
                if ((int) $storedItem->product_variant_id !== $requestedItem['product_variant_id']
                    || bccomp((string) $storedItem->ordered_quantity, $requestedItem['ordered_quantity'], 3) !== 0
                    || bccomp((string) $storedItem->expected_unit_cost, $requestedItem['expected_unit_cost'], 2) !== 0) {
                    $equivalent = false;
                    break;
                }
            }
        }

        if (! $equivalent) {
            throw ValidationException::withMessages([
                'submission_token' => 'This submission token is already associated with a different Purchase Order.',
            ]);
        }

        return $purchaseOrder;
    }

    private function isSubmissionTokenConstraint(UniqueConstraintViolationException $exception): bool
    {
        if ($exception->index === 'purchase_orders_submission_token_unique') {
            return true;
        }

        $columns = $exception->columns;
        sort($columns);

        return $columns === ['submission_token'];
    }
}

final class PurchaseOrderSubmissionTokenCollision extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('A Purchase Order submission-token collision occurred.', previous: $previous);
    }
}

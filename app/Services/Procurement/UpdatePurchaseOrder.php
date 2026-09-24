<?php

namespace App\Services\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemTransfer;
use App\Models\RestockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class UpdatePurchaseOrder
{
    private const REVISION_PATTERN = '/\A[0-9a-f]{64}\z/D';

    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_QUANTITY = '99999999999.999';

    private const MAX_COST = '9999999999.99';

    public function execute(
        User $actor,
        PurchaseOrder $purchaseOrder,
        mixed $expectedRevision,
        mixed $supplierName,
        mixed $notes,
        mixed $submittedItems,
    ): PurchaseOrder {
        $operation = $this->canonicalizeOperation(
            $actor,
            $purchaseOrder,
            $expectedRevision,
            $supplierName,
            $notes,
            $submittedItems,
        );
        $plan = $this->deriveLockPlan($operation['purchase_order_id'], array_keys($operation['items']));

        return DB::transaction(fn (): PurchaseOrder => $this->update($operation, $plan));
    }

    public function revision(PurchaseOrder $purchaseOrder): string
    {
        $purchaseOrderId = $this->purchaseOrderId($purchaseOrder);
        $authoritative = PurchaseOrder::query()->find($purchaseOrderId);
        if ($authoritative === null) {
            throw ValidationException::withMessages(['purchase_order' => 'The Purchase Order no longer exists.']);
        }

        $items = PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderBy('product_variant_id')
            ->orderBy('id')
            ->get();

        return $this->revisionFromState($authoritative, $items);
    }

    /**
     * @param  array{
     *     actor_id: int,
     *     purchase_order_id: int,
     *     expected_revision: string,
     *     supplier_name: string,
     *     notes: string|null,
     *     items: array<int, array{index: int, product_variant_id: int, ordered_quantity: string, expected_unit_cost: string}>
     * }  $operation
     * @param  array{
     *     pre_item_ids: list<int>,
     *     pre_variant_ids: list<int>,
     *     variant_ids: list<int>,
     *     product_ids: list<int>,
     *     category_ids: list<int>,
     *     new_variant_ids: list<int>,
     *     variant_products: array<int, int>,
     *     product_categories: array<int, int>
     * }  $plan
     */
    private function update(array $operation, array $plan): PurchaseOrder
    {
        $actor = User::query()
            ->whereKey($operation['actor_id'])
            ->lockForUpdate()
            ->first(['id', 'role', 'status']);
        if ($actor === null || ! $actor->isActive() || ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }

        $categories = Category::query()
            ->whereKey($plan['category_ids'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'status'])
            ->keyBy('id');
        $products = Product::query()
            ->whereKey($plan['product_ids'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'category_id', 'name', 'status'])
            ->keyBy('id');
        $variants = ProductVariant::query()
            ->whereKey($plan['variant_ids'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get([
                'id', 'product_id', 'size', 'type_series', 'thickness', 'unit',
                'quantity_mode', 'status',
            ])
            ->keyBy('id');

        $initializedNewVariantIds = StockMovement::query()
            ->whereIn('product_variant_id', $plan['new_variant_ids'])
            ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $lockedPurchaseOrder = PurchaseOrder::query()
            ->whereKey($operation['purchase_order_id'])
            ->lockForUpdate()
            ->first();
        if ($lockedPurchaseOrder === null) {
            throw ValidationException::withMessages(['purchase_order' => 'The Purchase Order no longer exists.']);
        }
        if (! $lockedPurchaseOrder->isEditable()) {
            throw ValidationException::withMessages(['purchase_order' => 'Only a pending Purchase Order may be edited.']);
        }

        $lockedItems = PurchaseOrderItem::query()
            ->where('purchase_order_id', $lockedPurchaseOrder->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $acceptedReceiving = RestockItem::query()
            ->whereIn('purchase_order_item_id', $lockedItems->pluck('id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']);
        if ($acceptedReceiving !== null) {
            throw ValidationException::withMessages([
                'purchase_order' => 'A Purchase Order with accepted receiving cannot be edited.',
            ]);
        }

        $itemIds = $lockedItems->pluck('id');
        $transfer = PurchaseOrderItemTransfer::query()
            ->where(function ($query) use ($itemIds): void {
                $query->whereIn('source_purchase_order_item_id', $itemIds)
                    ->orWhereIn('target_purchase_order_item_id', $itemIds);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']);
        if ($transfer !== null) {
            throw ValidationException::withMessages([
                'purchase_order' => 'A Purchase Order with transfer activity cannot be edited.',
            ]);
        }

        $lockedItemIds = $itemIds->map(fn ($id): int => (int) $id)->all();
        $lockedVariantIds = $lockedItems->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        if ($lockedItemIds !== $plan['pre_item_ids'] || $lockedVariantIds !== $plan['pre_variant_ids']) {
            throw ValidationException::withMessages([
                'expected_revision' => 'This Purchase Order changed while the form was open. Review it and try again.',
            ]);
        }

        $lockedRevision = $this->revisionFromState($lockedPurchaseOrder, $lockedItems);
        if (! hash_equals($lockedRevision, $operation['expected_revision'])) {
            throw ValidationException::withMessages([
                'expected_revision' => 'This Purchase Order changed while the form was open. Review it and try again.',
            ]);
        }

        $this->validateLockedMappings($operation, $plan, $categories, $products, $variants);

        $existingByVariant = $lockedItems->keyBy(fn (PurchaseOrderItem $item): int => (int) $item->product_variant_id);
        $newVariantIds = array_values(array_diff(array_keys($operation['items']), $lockedVariantIds));
        sort($newVariantIds, SORT_NUMERIC);
        if ($newVariantIds !== $plan['new_variant_ids']) {
            throw ValidationException::withMessages([
                'expected_revision' => 'This Purchase Order changed while the form was open. Review it and try again.',
            ]);
        }

        foreach ($operation['items'] as $variantId => $itemData) {
            $variant = $variants->get($variantId);
            if ($variant === null) {
                throw ValidationException::withMessages([
                    "items.{$itemData['index']}.product_variant_id" => 'The selected product variant no longer exists.',
                ]);
            }

            $this->validateQuantityMode(
                $itemData['ordered_quantity'],
                (string) $variant->quantity_mode,
                $itemData['index'],
            );

            if (! in_array($variantId, $newVariantIds, true)) {
                continue;
            }

            $product = $products->get((int) $variant->product_id);
            $category = $product === null ? null : $categories->get((int) $product->category_id);
            if ($product === null || $category === null
                || $category->status !== Category::STATUS_ACTIVE
                || $product->status !== Product::STATUS_ACTIVE
                || $variant->status !== ProductVariant::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    "items.{$itemData['index']}.product_variant_id" => 'New Purchase Order lines require an active category, product, and variant.',
                ]);
            }
            if (! in_array($variantId, $initializedNewVariantIds, true)) {
                throw ValidationException::withMessages([
                    "items.{$itemData['index']}.product_variant_id" => 'Opening inventory must be completed before adding this Purchase Order line.',
                ]);
            }
        }

        $lockedPurchaseOrder->supplier_name = $operation['supplier_name'];
        $lockedPurchaseOrder->notes = $operation['notes'];
        if ($lockedPurchaseOrder->isDirty(['supplier_name', 'notes'])) {
            $lockedPurchaseOrder->save();
        }

        foreach ($operation['items'] as $variantId => $itemData) {
            $item = $existingByVariant->get($variantId);
            if ($item === null) {
                continue;
            }

            $item->ordered_quantity = $itemData['ordered_quantity'];
            $item->expected_unit_cost = $itemData['expected_unit_cost'];
            if ($item->isDirty(['ordered_quantity', 'expected_unit_cost'])) {
                $item->save();
            }
        }

        foreach ($lockedItems->sortBy('product_variant_id') as $item) {
            if (! isset($operation['items'][(int) $item->product_variant_id])) {
                $item->delete();
            }
        }

        foreach ($newVariantIds as $variantId) {
            $variant = $variants->get($variantId);
            $product = $products->get((int) $variant->product_id);
            $itemData = $operation['items'][$variantId];

            $item = new PurchaseOrderItem;
            $item->purchase_order_id = $lockedPurchaseOrder->getKey();
            $item->product_variant_id = $variant->getKey();
            $item->product_name_snapshot = $product->name;
            $item->size_snapshot = $variant->size;
            $item->type_series_snapshot = $variant->type_series;
            $item->thickness_snapshot = $variant->thickness;
            $item->unit_snapshot = $variant->unit;
            $item->ordered_quantity = $itemData['ordered_quantity'];
            $item->expected_unit_cost = $itemData['expected_unit_cost'];
            $item->save();
        }

        $updatedItems = PurchaseOrderItem::query()
            ->where('purchase_order_id', $lockedPurchaseOrder->getKey())
            ->orderBy('product_variant_id')
            ->orderBy('id')
            ->get();
        $lockedPurchaseOrder->setRelation('items', $updatedItems);

        return $lockedPurchaseOrder;
    }

    /**
     * @param  array{
     *     actor_id: int,
     *     purchase_order_id: int,
     *     expected_revision: string,
     *     supplier_name: string,
     *     notes: string|null,
     *     items: array<int, array{index: int, product_variant_id: int, ordered_quantity: string, expected_unit_cost: string}>
     * }  $operation
     * @param  array{
     *     pre_item_ids: list<int>,
     *     pre_variant_ids: list<int>,
     *     variant_ids: list<int>,
     *     product_ids: list<int>,
     *     category_ids: list<int>,
     *     new_variant_ids: list<int>,
     *     variant_products: array<int, int>,
     *     product_categories: array<int, int>
     * }  $plan
     * @param  EloquentCollection<int, Category>  $categories
     * @param  EloquentCollection<int, Product>  $products
     * @param  EloquentCollection<int, ProductVariant>  $variants
     */
    private function validateLockedMappings(
        array $operation,
        array $plan,
        EloquentCollection $categories,
        EloquentCollection $products,
        EloquentCollection $variants,
    ): void {
        foreach ($plan['variant_ids'] as $variantId) {
            $variant = $variants->get($variantId);
            $expectedProductId = $plan['variant_products'][$variantId] ?? null;
            $product = $expectedProductId === null ? null : $products->get($expectedProductId);
            $expectedCategoryId = $expectedProductId === null ? null : ($plan['product_categories'][$expectedProductId] ?? null);
            $category = $expectedCategoryId === null ? null : $categories->get($expectedCategoryId);
            $index = $operation['items'][$variantId]['index'] ?? null;

            if ($variant === null || $product === null || $category === null
                || (int) $variant->product_id !== $expectedProductId
                || (int) $product->category_id !== $expectedCategoryId) {
                $key = $index === null ? 'expected_revision' : "items.{$index}.product_variant_id";
                throw ValidationException::withMessages([
                    $key => 'The catalog hierarchy changed. Review the Purchase Order and try again.',
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $submittedVariantIds
     * @return array{
     *     pre_item_ids: list<int>,
     *     pre_variant_ids: list<int>,
     *     variant_ids: list<int>,
     *     product_ids: list<int>,
     *     category_ids: list<int>,
     *     new_variant_ids: list<int>,
     *     variant_products: array<int, int>,
     *     product_categories: array<int, int>
     * }
     */
    private function deriveLockPlan(int $purchaseOrderId, array $submittedVariantIds): array
    {
        if (! PurchaseOrder::query()->whereKey($purchaseOrderId)->exists()) {
            throw ValidationException::withMessages(['purchase_order' => 'The Purchase Order no longer exists.']);
        }

        $preItems = PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderBy('id')
            ->get(['id', 'product_variant_id']);
        $preItemIds = $preItems->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $preVariantIds = $preItems->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $variantIds = array_values(array_unique(array_merge($preVariantIds, $submittedVariantIds)));
        sort($variantIds, SORT_NUMERIC);

        $variantMappings = ProductVariant::query()
            ->whereKey($variantIds)
            ->get(['id', 'product_id']);
        $variantProducts = [];
        foreach ($variantMappings as $variant) {
            $variantProducts[(int) $variant->getKey()] = (int) $variant->product_id;
        }
        $productIds = array_values(array_unique(array_values($variantProducts)));
        sort($productIds, SORT_NUMERIC);

        $productMappings = Product::query()
            ->whereKey($productIds)
            ->get(['id', 'category_id']);
        $productCategories = [];
        foreach ($productMappings as $product) {
            $productCategories[(int) $product->getKey()] = (int) $product->category_id;
        }
        $categoryIds = array_values(array_unique(array_values($productCategories)));
        sort($categoryIds, SORT_NUMERIC);

        $newVariantIds = array_values(array_diff($submittedVariantIds, $preVariantIds));
        sort($newVariantIds, SORT_NUMERIC);

        return [
            'pre_item_ids' => $preItemIds,
            'pre_variant_ids' => $preVariantIds,
            'variant_ids' => $variantIds,
            'product_ids' => $productIds,
            'category_ids' => $categoryIds,
            'new_variant_ids' => $newVariantIds,
            'variant_products' => $variantProducts,
            'product_categories' => $productCategories,
        ];
    }

    /**
     * @return array{
     *     actor_id: int,
     *     purchase_order_id: int,
     *     expected_revision: string,
     *     supplier_name: string,
     *     notes: string|null,
     *     items: array<int, array{index: int, product_variant_id: int, ordered_quantity: string, expected_unit_cost: string}>
     * }
     */
    private function canonicalizeOperation(
        User $actor,
        PurchaseOrder $purchaseOrder,
        mixed $expectedRevision,
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

        $purchaseOrderId = $this->purchaseOrderId($purchaseOrder);

        if (! is_string($expectedRevision)) {
            throw ValidationException::withMessages(['expected_revision' => 'The Purchase Order revision is invalid.']);
        }
        $revision = strtolower(trim($expectedRevision));
        if (preg_match(self::REVISION_PATTERN, $revision) !== 1) {
            throw ValidationException::withMessages(['expected_revision' => 'The Purchase Order revision is invalid.']);
        }

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
            'actor_id' => (int) $persistedActor->getKey(),
            'purchase_order_id' => $purchaseOrderId,
            'expected_revision' => $revision,
            'supplier_name' => $this->normalizeText($supplierName, 'supplier_name', 150, true),
            'notes' => $this->normalizeText($notes, 'notes', 1000, false),
            'items' => $items,
        ];
    }

    private function purchaseOrderId(PurchaseOrder $purchaseOrder): int
    {
        $purchaseOrderId = filter_var($purchaseOrder->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($purchaseOrderId === false) {
            throw ValidationException::withMessages(['purchase_order' => 'A persisted Purchase Order is required.']);
        }

        return (int) $purchaseOrderId;
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

    /** @param EloquentCollection<int, PurchaseOrderItem> $items */
    private function revisionFromState(PurchaseOrder $purchaseOrder, EloquentCollection $items): string
    {
        $semanticItems = $items
            ->sortBy([
                ['product_variant_id', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(fn (PurchaseOrderItem $item): array => [
                'purchase_order_item_id' => (int) $item->getKey(),
                'product_variant_id' => (int) $item->product_variant_id,
                'ordered_quantity' => $this->canonicalStoredDecimal((string) $item->ordered_quantity, 3),
                'expected_unit_cost' => $this->canonicalStoredDecimal((string) $item->expected_unit_cost, 2),
            ])
            ->all();

        $state = [
            'purchase_order_id' => (int) $purchaseOrder->getKey(),
            'supplier_name' => $this->normalizeText($purchaseOrder->supplier_name, 'supplier_name', 150, true),
            'notes' => $this->normalizeText($purchaseOrder->notes, 'notes', 1000, false),
            'status' => (string) $purchaseOrder->status,
            'created_by' => (int) $purchaseOrder->created_by,
            'parent_purchase_order_id' => $purchaseOrder->parent_purchase_order_id === null
                ? null
                : (int) $purchaseOrder->parent_purchase_order_id,
            'items' => $semanticItems,
        ];

        try {
            $serialized = json_encode(
                $state,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw ValidationException::withMessages(['expected_revision' => 'The Purchase Order revision could not be calculated.']);
        }

        return hash('sha256', $serialized);
    }

    private function canonicalStoredDecimal(string $value, int $scale): string
    {
        $pattern = $scale === 3 ? self::QUANTITY_PATTERN : self::COST_PATTERN;
        if (preg_match($pattern, $value) !== 1) {
            throw ValidationException::withMessages(['expected_revision' => 'The Purchase Order contains invalid decimal data.']);
        }

        return $this->canonicalUnsigned($value, $scale);
    }
}

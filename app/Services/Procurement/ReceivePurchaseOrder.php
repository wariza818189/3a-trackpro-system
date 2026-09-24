<?php

namespace App\Services\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\PostRestockInventory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Throwable;

class ReceivePurchaseOrder
{
    private const TOKEN_PATTERN = '/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D';

    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_QUANTITY = '99999999999.999';

    private const MAX_MONEY = '99999999999999.99';

    public function __construct(private readonly PostRestockInventory $inventory) {}

    /** @param list<array<string, mixed>> $submittedItems */
    public function execute(
        User $actor,
        PurchaseOrder $purchaseOrder,
        mixed $submissionToken,
        mixed $referenceText,
        mixed $notes,
        array $submittedItems,
    ): Restock {
        $operation = $this->canonicalizeOperation(
            $actor,
            $purchaseOrder,
            $submissionToken,
            $referenceText,
            $notes,
            $submittedItems,
        );

        $existing = Restock::query()
            ->where('submission_token', $operation['submission_token'])
            ->with(['items' => fn ($query) => $query->orderBy('purchase_order_item_id')])
            ->first();
        if ($existing !== null) {
            return $this->resolveReplay($existing, $operation);
        }

        $plan = $this->deriveLockPlan($operation);

        try {
            return DB::transaction(fn (): Restock => $this->receive($operation, $plan));
        } catch (PurchaseOrderReceiptTokenCollision) {
            $winner = Restock::query()
                ->where('submission_token', $operation['submission_token'])
                ->lockForUpdate()
                ->first();
            if ($winner === null) {
                throw new LogicException('The committed Purchase Order receipt could not be resolved.');
            }

            $items = RestockItem::query()
                ->where('restock_id', $winner->getKey())
                ->orderBy('purchase_order_item_id')
                ->lockForUpdate()
                ->get();
            $winner->setRelation('items', $items);

            return $this->resolveReplay($winner, $operation);
        }
    }

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $plan
     */
    private function receive(array $operation, array $plan): Restock
    {
        $actor = User::query()
            ->whereKey($operation['actor_id'])
            ->lockForUpdate()
            ->first(['id', 'role', 'status']);
        if ($actor === null || ! $actor->isActive() || ! in_array($actor->role, [User::ROLE_ADMIN, 'staff'], true)) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin or Staff user is required.']);
        }

        $categories = Category::query()->whereKey($plan['category_ids'])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $products = Product::query()->whereKey($plan['product_ids'])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $variants = ProductVariant::query()->whereKey($plan['variant_ids'])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($categories->count() !== count($plan['category_ids'])
            || $products->count() !== count($plan['product_ids'])
            || $variants->count() !== count($plan['variant_ids'])) {
            throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Please retry.']);
        }

        foreach ($variants as $variant) {
            $product = $products->get((int) $variant->product_id);
            $category = $product === null ? null : $categories->get((int) $product->category_id);
            $expectedProductId = $plan['variant_products'][(int) $variant->getKey()] ?? null;
            $expectedCategoryId = $expectedProductId === null ? null : ($plan['product_categories'][$expectedProductId] ?? null);
            if ($product === null || $category === null
                || (int) $variant->product_id !== $expectedProductId
                || (int) $product->category_id !== $expectedCategoryId) {
                throw ValidationException::withMessages(['items' => 'The catalog hierarchy changed. Please retry.']);
            }
            if ($category->status !== Category::STATUS_ACTIVE
                || $product->status !== Product::STATUS_ACTIVE
                || $variant->status !== ProductVariant::STATUS_ACTIVE) {
                $index = $operation['items'][$plan['variant_items'][(int) $variant->getKey()]]['index'];
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_order_item_id" => 'Receiving requires an active category, product, and variant.',
                ]);
            }
        }

        $initializedIds = StockMovement::query()
            ->whereIn('product_variant_id', $plan['variant_ids'])
            ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();
        foreach ($plan['variant_ids'] as $variantId) {
            if (! in_array($variantId, $initializedIds, true)) {
                $index = $operation['items'][$plan['variant_items'][$variantId]]['index'];
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_order_item_id" => 'Opening inventory must be completed before receiving.',
                ]);
            }
        }

        $purchaseOrder = PurchaseOrder::query()
            ->whereKey($operation['purchase_order_id'])
            ->lockForUpdate()
            ->first();
        if ($purchaseOrder === null) {
            throw ValidationException::withMessages(['purchase_order' => 'The Purchase Order no longer exists.']);
        }
        $purchaseOrderItems = PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrder->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        foreach ($operation['items'] as $purchaseOrderItemId => $itemData) {
            $purchaseOrderItem = $purchaseOrderItems->get($purchaseOrderItemId);
            if ($purchaseOrderItem === null
                || (int) $purchaseOrderItem->product_variant_id !== $plan['item_variants'][$purchaseOrderItemId]) {
                throw ValidationException::withMessages([
                    "items.{$itemData['index']}.purchase_order_item_id" => 'The selected item does not belong to this Purchase Order.',
                ]);
            }
        }

        $acceptedEvidence = RestockItem::query()
            ->whereIn('purchase_order_item_id', $purchaseOrderItems->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $acceptedByItem = [];
        foreach ($acceptedEvidence as $evidence) {
            $itemId = (int) $evidence->purchase_order_item_id;
            $acceptedByItem[$itemId] = bcadd($acceptedByItem[$itemId] ?? '0.000', (string) $evidence->quantity, 3);
        }

        $tokenRestock = Restock::query()
            ->where('submission_token', $operation['submission_token'])
            ->lockForUpdate()
            ->first();
        if ($tokenRestock !== null) {
            $tokenRestock->setRelation('items', RestockItem::query()
                ->where('restock_id', $tokenRestock->getKey())
                ->orderBy('purchase_order_item_id')
                ->lockForUpdate()
                ->get());

            return $this->resolveReplay($tokenRestock, $operation);
        }
        if (! in_array($purchaseOrder->status, PurchaseOrder::OPEN_STATUSES, true)) {
            throw ValidationException::withMessages(['purchase_order' => 'Only a pending or partially received Purchase Order may be received.']);
        }

        foreach ($operation['items'] as $purchaseOrderItemId => $itemData) {
            $purchaseOrderItem = $purchaseOrderItems->get($purchaseOrderItemId);
            $outstanding = bcsub((string) $purchaseOrderItem->ordered_quantity, $acceptedByItem[$purchaseOrderItemId] ?? '0.000', 3);
            if (bccomp($itemData['quantity'], $outstanding, 3) === 1) {
                throw ValidationException::withMessages([
                    "items.{$itemData['index']}.accepted_quantity" => 'The accepted quantity exceeds the current outstanding quantity.',
                ]);
            }
        }

        $restock = new Restock;
        $restock->submission_token = $operation['submission_token'];
        $restock->recorded_by = $operation['actor_id'];
        $restock->purchase_order_id = $purchaseOrder->getKey();
        $restock->reference_text = $operation['reference_text'];
        $restock->notes = $operation['notes'];
        $restock->total_cost = $operation['total_cost'];
        try {
            $restock->save();
        } catch (QueryException $exception) {
            if (! $this->isSubmissionTokenDuplicate($exception)) {
                throw $exception;
            }

            throw new PurchaseOrderReceiptTokenCollision($exception);
        }

        $postingItems = [];
        foreach ($operation['items'] as $purchaseOrderItemId => $itemData) {
            $variantId = $plan['item_variants'][$purchaseOrderItemId];
            $postingItems[$variantId] = $itemData + ['purchase_order_item_id' => $purchaseOrderItemId];
            $acceptedByItem[$purchaseOrderItemId] = bcadd(
                $acceptedByItem[$purchaseOrderItemId] ?? '0.000',
                $itemData['quantity'],
                3,
            );
        }
        ksort($postingItems, SORT_NUMERIC);
        $restock->setRelation('items', $this->inventory->post(
            $restock,
            $operation['actor_id'],
            $variants,
            $products,
            $postingItems,
        ));

        $hasOutstanding = false;
        foreach ($purchaseOrderItems as $purchaseOrderItem) {
            $accepted = $acceptedByItem[(int) $purchaseOrderItem->getKey()] ?? '0.000';
            if (bccomp((string) $purchaseOrderItem->ordered_quantity, $accepted, 3) === 1) {
                $hasOutstanding = true;
                break;
            }
        }
        $purchaseOrder->status = $hasOutstanding
            ? PurchaseOrder::STATUS_PARTIALLY_RECEIVED
            : PurchaseOrder::STATUS_COMPLETED;
        $purchaseOrder->save();

        return $restock;
    }

    /** @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function deriveLockPlan(array $operation): array
    {
        if (! PurchaseOrder::query()->whereKey($operation['purchase_order_id'])->exists()) {
            throw ValidationException::withMessages(['purchase_order' => 'The Purchase Order no longer exists.']);
        }

        $itemMappings = PurchaseOrderItem::query()
            ->whereKey(array_keys($operation['items']))
            ->get(['id', 'product_variant_id']);
        if ($itemMappings->count() !== count($operation['items'])) {
            throw ValidationException::withMessages(['items' => 'One or more selected Purchase Order items no longer exist.']);
        }
        $itemVariants = [];
        $variantItems = [];
        foreach ($itemMappings as $item) {
            $itemVariants[(int) $item->getKey()] = (int) $item->product_variant_id;
            $variantItems[(int) $item->product_variant_id] = (int) $item->getKey();
        }
        ksort($itemVariants, SORT_NUMERIC);
        $variantIds = array_values(array_unique(array_values($itemVariants)));
        sort($variantIds, SORT_NUMERIC);

        $variantMappings = ProductVariant::query()->whereKey($variantIds)->get(['id', 'product_id']);
        $variantProducts = [];
        foreach ($variantMappings as $variant) {
            $variantProducts[(int) $variant->getKey()] = (int) $variant->product_id;
        }
        $productIds = array_values(array_unique(array_values($variantProducts)));
        sort($productIds, SORT_NUMERIC);

        $productMappings = Product::query()->whereKey($productIds)->get(['id', 'category_id']);
        $productCategories = [];
        foreach ($productMappings as $product) {
            $productCategories[(int) $product->getKey()] = (int) $product->category_id;
        }
        $categoryIds = array_values(array_unique(array_values($productCategories)));
        sort($categoryIds, SORT_NUMERIC);

        return [
            'item_variants' => $itemVariants,
            'variant_items' => $variantItems,
            'variant_ids' => $variantIds,
            'product_ids' => $productIds,
            'category_ids' => $categoryIds,
            'variant_products' => $variantProducts,
            'product_categories' => $productCategories,
        ];
    }

    /** @param list<array<string, mixed>> $submittedItems
     * @return array<string, mixed>
     */
    private function canonicalizeOperation(
        User $actor,
        PurchaseOrder $purchaseOrder,
        mixed $submissionToken,
        mixed $referenceText,
        mixed $notes,
        array $submittedItems,
    ): array {
        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $persistedActor = $actorId === false ? null : User::query()->find((int) $actorId, ['id', 'role', 'status']);
        if ($persistedActor === null || ! $persistedActor->isActive()
            || ! in_array($persistedActor->role, [User::ROLE_ADMIN, 'staff'], true)) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin or Staff user is required.']);
        }

        $purchaseOrderId = filter_var($purchaseOrder->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($purchaseOrderId === false) {
            throw ValidationException::withMessages(['purchase_order' => 'A persisted Purchase Order is required.']);
        }

        if (! is_string($submissionToken)) {
            throw ValidationException::withMessages(['submission_token' => 'The receiving submission token is invalid.']);
        }
        $token = trim($submissionToken);
        if (strlen($token) !== 36 || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw ValidationException::withMessages(['submission_token' => 'The receiving submission token is invalid.']);
        }
        $token = strtolower($token);

        if (count($submittedItems) < 1 || count($submittedItems) > 100) {
            throw ValidationException::withMessages(['items' => 'Receiving requires between 1 and 100 accepted items.']);
        }

        $items = [];
        $total = '0.00';
        foreach (array_values($submittedItems) as $index => $submitted) {
            if (! is_array($submitted)) {
                throw ValidationException::withMessages(["items.{$index}" => 'Each received item must be an object.']);
            }
            $keys = array_keys($submitted);
            sort($keys);
            if ($keys !== ['accepted_quantity', 'actual_unit_cost', 'purchase_order_item_id']) {
                throw ValidationException::withMessages(["items.{$index}" => 'The received item contains unexpected fields.']);
            }

            $itemId = filter_var($submitted['purchase_order_item_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($itemId === false) {
                throw ValidationException::withMessages(["items.{$index}.purchase_order_item_id" => 'Select a valid Purchase Order item.']);
            }
            if (isset($items[$itemId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_order_item_id" => 'A Purchase Order item may appear only once in a receipt.',
                ]);
            }

            $quantity = $this->canonicalQuantity($submitted['accepted_quantity'], $index);
            $unitCost = $this->canonicalCost($submitted['actual_unit_cost'], $index);
            $lineTotal = bcadd(bcmul($quantity, $unitCost, 5), '0.005', 2);
            if (bccomp($lineTotal, self::MAX_MONEY, 2) === 1) {
                throw ValidationException::withMessages(["items.{$index}.actual_unit_cost" => 'The item total is too large.']);
            }
            $total = bcadd($total, $lineTotal, 2);
            if (bccomp($total, self::MAX_MONEY, 2) === 1) {
                throw ValidationException::withMessages(['items' => 'The receiving total is too large.']);
            }

            $items[(int) $itemId] = [
                'index' => $index,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }
        ksort($items, SORT_NUMERIC);

        return [
            'actor_id' => (int) $persistedActor->getKey(),
            'purchase_order_id' => (int) $purchaseOrderId,
            'submission_token' => $token,
            'reference_text' => $this->normalizeHeaderText($referenceText, 'reference_text'),
            'notes' => $this->normalizeHeaderText($notes, 'notes'),
            'total_cost' => $total,
            'items' => $items,
        ];
    }

    private function canonicalQuantity(mixed $value, int $index): string
    {
        if (! is_string($value) || preg_match(self::QUANTITY_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.accepted_quantity" => 'Enter a positive ordinary decimal with up to three decimal places.']);
        }
        $canonical = $this->canonicalUnsigned(trim($value), 3);
        if (strlen(strtok($canonical, '.')) > 11 || bccomp($canonical, self::MAX_QUANTITY, 3) === 1) {
            throw ValidationException::withMessages(["items.{$index}.accepted_quantity" => 'The accepted quantity is too large.']);
        }
        if (bccomp($canonical, '0.000', 3) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.accepted_quantity" => 'The accepted quantity must be greater than zero.']);
        }

        return $canonical;
    }

    private function canonicalCost(mixed $value, int $index): string
    {
        if (! is_string($value) || preg_match(self::COST_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.actual_unit_cost" => 'Enter a nonnegative ordinary decimal with up to two decimal places.']);
        }
        $canonical = $this->canonicalUnsigned(trim($value), 2);
        if (strlen(strtok($canonical, '.')) > 10 || bccomp($canonical, '9999999999.99', 2) === 1) {
            throw ValidationException::withMessages(["items.{$index}.actual_unit_cost" => 'The actual unit cost is too large.']);
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

    /** @param array<string, mixed> $operation */
    private function resolveReplay(Restock $restock, array $operation): Restock
    {
        $persistedItems = $restock->items
            ->sortBy('purchase_order_item_id')
            ->values()
            ->map(fn (RestockItem $item): array => [
                'purchase_order_item_id' => (int) $item->purchase_order_item_id,
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
                'line_total' => (string) $item->line_total,
            ])->all();
        $requestedItems = collect($operation['items'])->map(fn (array $item, int $itemId): array => [
            'purchase_order_item_id' => $itemId,
            'quantity' => $item['quantity'],
            'unit_cost' => $item['unit_cost'],
            'line_total' => $item['line_total'],
        ])->values()->all();

        if ((int) $restock->recorded_by !== $operation['actor_id']
            || (int) $restock->purchase_order_id !== $operation['purchase_order_id']
            || $restock->reference_text !== $operation['reference_text']
            || $restock->notes !== $operation['notes']
            || (string) $restock->total_cost !== $operation['total_cost']
            || $persistedItems !== $requestedItems) {
            throw ValidationException::withMessages([
                'submission_token' => 'This receiving submission token cannot be reused.',
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

final class PurchaseOrderReceiptTokenCollision extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('A Purchase Order receiving submission-token collision occurred.', previous: $previous);
    }
}

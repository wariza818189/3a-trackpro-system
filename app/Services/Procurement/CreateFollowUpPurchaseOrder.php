<?php

namespace App\Services\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemTransfer;
use App\Models\RestockItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Throwable;

class CreateFollowUpPurchaseOrder
{
    private const TOKEN_PATTERN = '/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D';

    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_COST = '9999999999.99';

    public function execute(
        User $actor,
        PurchaseOrder $source,
        mixed $submissionToken,
        mixed $supplierName,
        mixed $notes,
        mixed $submittedItems,
    ): PurchaseOrder {
        $operation = $this->canonicalizeOperation($actor, $source, $submissionToken, $supplierName, $notes, $submittedItems);

        $existing = DB::transaction(function () use ($operation): ?PurchaseOrder {
            $this->lockActor($operation['actor_id']);
            $purchaseOrder = $this->findByTokenForUpdate($operation['submission_token']);

            return $purchaseOrder === null ? null : $this->resolveReplay($purchaseOrder, $operation);
        });
        if ($existing !== null) {
            return $existing;
        }

        // This read plans Variant locks before any source PO lock. The mapping is
        // rechecked after locking the source items; a stale plan is never extended.
        $variantPlan = $this->deriveVariantPlan($operation);

        try {
            return DB::transaction(fn (): PurchaseOrder => $this->create($operation, $variantPlan));
        } catch (FollowUpSubmissionTokenCollision) {
            return DB::transaction(function () use ($operation): PurchaseOrder {
                $this->lockActor($operation['actor_id']);
                $winner = $this->findByTokenForUpdate($operation['submission_token']);
                if ($winner === null) {
                    throw new LogicException('The committed follow-up Purchase Order submission could not be resolved.');
                }

                return $this->resolveReplay($winner, $operation);
            });
        }
    }

    /** @param array<string, mixed> $operation
     * @param  array<int, int>  $variantPlan
     */
    private function create(array $operation, array $variantPlan): PurchaseOrder
    {
        $actor = $this->lockActor($operation['actor_id']);

        $variantIds = array_values(array_unique(array_values($variantPlan)));
        sort($variantIds, SORT_NUMERIC);
        $variants = ProductVariant::query()
            ->whereKey($variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->modelKeys();
        if (array_map('intval', $variants) !== $variantIds) {
            throw ValidationException::withMessages(['items' => 'A selected Variant changed. Review the source Purchase Order and try again.']);
        }

        $source = PurchaseOrder::query()
            ->whereKey($operation['source_purchase_order_id'])
            ->lockForUpdate()
            ->first();
        if ($source === null) {
            throw ValidationException::withMessages(['purchase_order' => 'The source Purchase Order no longer exists.']);
        }

        $sourceItems = PurchaseOrderItem::query()
            ->where('purchase_order_id', $source->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        foreach ($operation['items'] as $itemId => $submitted) {
            $item = $sourceItems->get($itemId);
            if ($item === null || (int) $item->product_variant_id !== $variantPlan[$itemId]) {
                throw ValidationException::withMessages([
                    "items.{$submitted['index']}.source_purchase_order_item_id" => 'The selected source line changed. Review the Purchase Order and try again.',
                ]);
            }
        }

        $acceptedEvidence = RestockItem::query()
            ->whereIn('purchase_order_item_id', $sourceItems->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['purchase_order_item_id', 'quantity']);
        $acceptedByItem = [];
        foreach ($acceptedEvidence as $evidence) {
            $itemId = (int) $evidence->purchase_order_item_id;
            $acceptedByItem[$itemId] = bcadd($acceptedByItem[$itemId] ?? '0.000', (string) $evidence->quantity, 3);
        }

        $outgoingEvidence = PurchaseOrderItemTransfer::query()
            ->whereIn('source_purchase_order_item_id', $sourceItems->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['source_purchase_order_item_id', 'quantity']);
        $transferredByItem = [];
        foreach ($outgoingEvidence as $evidence) {
            $itemId = (int) $evidence->source_purchase_order_item_id;
            $transferredByItem[$itemId] = bcadd($transferredByItem[$itemId] ?? '0.000', (string) $evidence->quantity, 3);
        }

        // Leave the source transaction before locking an existing child for
        // replay. Otherwise source transfer rows and child locks can reverse
        // the dedicated replay path's child-then-transfer order.
        if (PurchaseOrder::query()->where('submission_token', $operation['submission_token'])->exists()) {
            throw new FollowUpSubmissionTokenCollision;
        }
        if (! in_array($source->status, PurchaseOrder::OPEN_STATUSES, true)) {
            throw ValidationException::withMessages(['purchase_order' => 'Only an open Purchase Order can produce a follow-up.']);
        }

        $quantities = [];
        foreach ($operation['items'] as $itemId => $submitted) {
            if (isset($transferredByItem[$itemId])) {
                throw ValidationException::withMessages([
                    "items.{$submitted['index']}.source_purchase_order_item_id" => 'This source line was already transferred.',
                ]);
            }
            $item = $sourceItems->get($itemId);
            $quantity = bcsub(
                bcsub((string) $item->ordered_quantity, $acceptedByItem[$itemId] ?? '0.000', 3),
                $transferredByItem[$itemId] ?? '0.000',
                3,
            );
            if (bccomp($quantity, '0.000', 3) !== 1) {
                throw ValidationException::withMessages([
                    "items.{$submitted['index']}.source_purchase_order_item_id" => 'This source line has no outstanding quantity.',
                ]);
            }
            $quantities[$itemId] = $quantity;
        }

        $child = new PurchaseOrder;
        $child->parent_purchase_order_id = $source->getKey();
        $child->submission_token = $operation['submission_token'];
        $child->created_by = $actor->getKey();
        $child->supplier_name = $operation['supplier_name'];
        $child->notes = $operation['notes'];
        $child->status = PurchaseOrder::STATUS_PENDING;
        try {
            $child->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isSubmissionTokenConstraint($exception)) {
                throw $exception;
            }

            throw new FollowUpSubmissionTokenCollision($exception);
        }

        $createdItems = collect();
        $targetsBySource = [];
        foreach ($operation['items'] as $itemId => $submitted) {
            $sourceItem = $sourceItems->get($itemId);
            $targetItem = new PurchaseOrderItem;
            $targetItem->purchase_order_id = $child->getKey();
            $targetItem->product_variant_id = $sourceItem->product_variant_id;
            $targetItem->product_name_snapshot = $sourceItem->product_name_snapshot;
            $targetItem->size_snapshot = $sourceItem->size_snapshot;
            $targetItem->type_series_snapshot = $sourceItem->type_series_snapshot;
            $targetItem->thickness_snapshot = $sourceItem->thickness_snapshot;
            $targetItem->unit_snapshot = $sourceItem->unit_snapshot;
            $targetItem->ordered_quantity = $quantities[$itemId];
            $targetItem->expected_unit_cost = $submitted['expected_unit_cost'];
            $targetItem->save();
            $createdItems->push($targetItem);

            $targetsBySource[$itemId] = $targetItem;
        }

        foreach ($targetsBySource as $itemId => $targetItem) {
            PurchaseOrderItemTransfer::create([
                'source_purchase_order_item_id' => $itemId,
                'target_purchase_order_item_id' => $targetItem->getKey(),
                'quantity' => $quantities[$itemId],
                'created_by' => $actor->getKey(),
            ]);
            $transferredByItem[$itemId] = $quantities[$itemId];
        }

        $hasOutstanding = false;
        foreach ($sourceItems as $item) {
            $itemId = (int) $item->getKey();
            $outstanding = bcsub(
                bcsub((string) $item->ordered_quantity, $acceptedByItem[$itemId] ?? '0.000', 3),
                $transferredByItem[$itemId] ?? '0.000',
                3,
            );
            if (bccomp($outstanding, '0.000', 3) === 1) {
                $hasOutstanding = true;
                break;
            }
        }
        $source->status = $hasOutstanding
            ? PurchaseOrder::STATUS_PARTIALLY_RECEIVED
            : PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER;
        $source->save();
        $child->setRelation('items', $createdItems);

        return $child;
    }

    /** @param array<string, mixed> $operation
     * @return array<int, int>
     */
    private function deriveVariantPlan(array $operation): array
    {
        $items = PurchaseOrderItem::query()
            ->whereKey(array_keys($operation['items']))
            ->get(['id', 'product_variant_id'])
            ->keyBy('id');
        if ($items->count() !== count($operation['items'])) {
            throw ValidationException::withMessages(['items' => 'One or more selected source lines no longer exist.']);
        }

        $plan = [];
        foreach ($operation['items'] as $itemId => $ignored) {
            $plan[$itemId] = (int) $items->get($itemId)->product_variant_id;
        }

        return $plan;
    }

    /** @return array<string, mixed> */
    private function canonicalizeOperation(
        User $actor,
        PurchaseOrder $source,
        mixed $submissionToken,
        mixed $supplierName,
        mixed $notes,
        mixed $submittedItems,
    ): array {
        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actorId === false) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }
        $sourceId = filter_var($source->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($sourceId === false) {
            throw ValidationException::withMessages(['purchase_order' => 'A persisted source Purchase Order is required.']);
        }
        if (! is_string($submissionToken)) {
            throw ValidationException::withMessages(['submission_token' => 'The follow-up submission token is invalid.']);
        }
        $token = trim($submissionToken);
        if (strlen($token) !== 36 || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw ValidationException::withMessages(['submission_token' => 'The follow-up submission token is invalid.']);
        }
        if (! is_array($submittedItems) || count($submittedItems) < 1 || count($submittedItems) > 100) {
            throw ValidationException::withMessages(['items' => 'A follow-up Purchase Order requires between 1 and 100 source lines.']);
        }

        $items = [];
        foreach (array_values($submittedItems) as $index => $submitted) {
            if (! is_array($submitted)) {
                throw ValidationException::withMessages(["items.{$index}" => 'Each selected source line must be an object.']);
            }
            $keys = array_keys($submitted);
            sort($keys);
            if ($keys !== ['expected_unit_cost', 'source_purchase_order_item_id']) {
                throw ValidationException::withMessages(["items.{$index}" => 'The selected source line contains unexpected fields.']);
            }
            $itemId = filter_var($submitted['source_purchase_order_item_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($itemId === false) {
                throw ValidationException::withMessages(["items.{$index}.source_purchase_order_item_id" => 'Select a valid source line.']);
            }
            if (isset($items[(int) $itemId])) {
                throw ValidationException::withMessages(["items.{$index}.source_purchase_order_item_id" => 'A source line may be selected only once.']);
            }
            $items[(int) $itemId] = [
                'index' => $index,
                'expected_unit_cost' => $this->canonicalCost($submitted['expected_unit_cost'], $index),
            ];
        }
        ksort($items, SORT_NUMERIC);

        return [
            'actor_id' => (int) $actorId,
            'source_purchase_order_id' => (int) $sourceId,
            'submission_token' => strtolower($token),
            'supplier_name' => $this->normalizeText($supplierName, 'supplier_name', 150, true),
            'notes' => $this->normalizeText($notes, 'notes', 1000, false),
            'items' => $items,
        ];
    }

    private function canonicalCost(mixed $value, int $index): string
    {
        if (! is_string($value) || preg_match(self::COST_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages(["items.{$index}.expected_unit_cost" => 'Enter a nonnegative ordinary decimal with up to two decimal places.']);
        }
        $value = trim($value);
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');
        $cost = ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, 2, '0');
        if (strlen(strtok($cost, '.')) > 10 || bccomp($cost, self::MAX_COST, 2) === 1) {
            throw ValidationException::withMessages(["items.{$index}.expected_unit_cost" => 'The expected unit cost is too large.']);
        }

        return $cost;
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

    private function lockActor(int $actorId): User
    {
        $actor = User::query()->whereKey($actorId)->lockForUpdate()->first(['id', 'role', 'status']);
        if ($actor === null || ! $actor->isActive() || ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }

        return $actor;
    }

    private function findByTokenForUpdate(string $token): ?PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::query()->where('submission_token', $token)->lockForUpdate()->first();
        if ($purchaseOrder === null) {
            return null;
        }
        $items = PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrder->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $purchaseOrder->setRelation('items', $items);
        $transfers = PurchaseOrderItemTransfer::query()
            ->whereIn('target_purchase_order_item_id', $items->modelKeys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $purchaseOrder->setRelation('incomingTransfers', $transfers);

        return $purchaseOrder;
    }

    /** @param array<string, mixed> $operation */
    private function resolveReplay(PurchaseOrder $purchaseOrder, array $operation): PurchaseOrder
    {
        $items = $purchaseOrder->items;
        $transfers = $purchaseOrder->getRelation('incomingTransfers')->keyBy('target_purchase_order_item_id');
        $equivalent = (int) $purchaseOrder->created_by === $operation['actor_id']
            && (int) $purchaseOrder->parent_purchase_order_id === $operation['source_purchase_order_id']
            && $purchaseOrder->supplier_name === $operation['supplier_name']
            && $purchaseOrder->notes === $operation['notes']
            && $items->count() === count($operation['items'])
            && $transfers->count() === $items->count();

        if ($equivalent) {
            $seen = [];
            foreach ($items as $item) {
                $transfer = $transfers->get($item->getKey());
                $sourceId = $transfer === null ? null : (int) $transfer->source_purchase_order_item_id;
                $submitted = $sourceId === null ? null : ($operation['items'][$sourceId] ?? null);
                if ($submitted === null || isset($seen[$sourceId])
                    || bccomp((string) $item->ordered_quantity, (string) $transfer->quantity, 3) !== 0
                    || bccomp((string) $item->expected_unit_cost, $submitted['expected_unit_cost'], 2) !== 0) {
                    $equivalent = false;
                    break;
                }
                $seen[$sourceId] = true;
            }
            $equivalent = $equivalent && count($seen) === count($operation['items']);
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

final class FollowUpSubmissionTokenCollision extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('A follow-up Purchase Order submission-token collision occurred.', previous: $previous);
    }
}

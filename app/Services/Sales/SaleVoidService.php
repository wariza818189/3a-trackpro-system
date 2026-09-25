<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleVoidService
{
    private const MAX_QUANTITY = '99999999999.999';

    public function execute(User $actor, Sale $sale, mixed $reason): Sale
    {
        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $saleId = filter_var($sale->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actorId === false) {
            throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
        }
        if ($saleId === false) {
            throw ValidationException::withMessages(['sale' => 'A persisted completed Sale is required.']);
        }

        $normalizedReason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($actorId, $saleId, $normalizedReason): Sale {
            $persistedActor = User::query()
                ->whereKey((int) $actorId)
                ->lockForUpdate()
                ->first(['id', 'role', 'status']);
            if ($persistedActor === null || ! $persistedActor->isActive() || ! $persistedActor->isAdmin()) {
                throw ValidationException::withMessages(['actor' => 'A persisted active Admin is required.']);
            }

            $persistedSale = Sale::query()->whereKey((int) $saleId)->lockForUpdate()->first();
            if ($persistedSale === null) {
                throw ValidationException::withMessages(['sale' => 'The selected Sale no longer exists.']);
            }
            if ($persistedSale->status !== Sale::STATUS_COMPLETED
                || $persistedSale->void_reason !== null
                || $persistedSale->voided_by !== null
                || $persistedSale->voided_at !== null) {
                throw ValidationException::withMessages(['sale' => 'Only a completed Sale may be voided once.']);
            }

            $voidedAt = now();
            if ($persistedSale->created_at === null || $voidedAt->lt($persistedSale->created_at)) {
                throw ValidationException::withMessages(['sale' => 'The Sale cannot be voided before it was recorded.']);
            }

            $items = SaleItem::query()
                ->where('sale_id', $persistedSale->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['sale' => 'A Sale without items cannot be voided.']);
            }

            $variantIds = $items->pluck('product_variant_id')->map(fn ($id): int => (int) $id)->all();
            if (count($variantIds) !== count(array_unique($variantIds))) {
                throw ValidationException::withMessages(['sale' => 'The Sale contains ambiguous duplicate Variant lines.']);
            }

            $variants = ProductVariant::query()
                ->whereKey($variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'current_stock'])
                ->keyBy('id');
            if ($variants->count() !== count($variantIds)) {
                throw ValidationException::withMessages(['sale' => 'One or more sold Variants no longer exist.']);
            }

            foreach ($items as $item) {
                $variant = $variants->get((int) $item->product_variant_id);
                $before = $this->canonicalQuantity((string) $variant->current_stock, false);
                $change = $this->canonicalQuantity((string) $item->quantity, true);
                $after = bcadd($before, $change, 3);
                if (bccomp($after, self::MAX_QUANTITY, 3) === 1) {
                    throw ValidationException::withMessages([
                        'sale' => 'Restoring this Sale would exceed the supported stock capacity.',
                    ]);
                }

                $variant->current_stock = $after;
                $variant->save();

                $movement = new StockMovement;
                $movement->product_variant_id = $variant->getKey();
                $movement->movement_type = StockMovement::TYPE_SALE_VOID;
                $movement->quantity_before = $before;
                $movement->quantity_change = $change;
                $movement->quantity_after = $after;
                $movement->performed_by = $persistedActor->getKey();
                $movement->sale_item_id = $item->getKey();
                $movement->restock_item_id = null;
                $movement->reason = $normalizedReason;
                $movement->created_at = $voidedAt;
                $movement->save();
            }

            if (! $persistedSale->transitionToVoided($persistedActor, $normalizedReason, $voidedAt)) {
                throw ValidationException::withMessages(['sale' => 'Only a completed Sale may be voided once.']);
            }

            AuditLog::query()->create([
                'user_id' => $persistedActor->getKey(),
                'action' => 'SALE_VOIDED',
                'entity_type' => 'sale',
                'entity_id' => $persistedSale->getKey(),
                'before_values' => ['status' => Sale::STATUS_COMPLETED],
                'after_values' => ['status' => Sale::STATUS_VOIDED],
                'description' => 'Sale #'.$persistedSale->getKey().' was voided.',
            ]);

            return $persistedSale;
        });
    }

    private function normalizeReason(mixed $reason): string
    {
        if (! is_string($reason)) {
            throw ValidationException::withMessages(['reason' => 'The reason must be text.']);
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($reason));
        if (! is_string($normalized)) {
            throw ValidationException::withMessages(['reason' => 'The reason contains invalid text.']);
        }
        if ($normalized === '') {
            throw ValidationException::withMessages(['reason' => 'A meaningful reason is required.']);
        }
        if (mb_strlen($normalized) > 1000) {
            throw ValidationException::withMessages(['reason' => 'The reason must not exceed 1000 characters.']);
        }

        return $normalized;
    }

    private function canonicalQuantity(string $quantity, bool $positive): string
    {
        $quantity = trim($quantity);
        if (preg_match('/\A\d+(?:\.\d{1,3})?\z/D', $quantity) !== 1) {
            throw ValidationException::withMessages(['sale' => 'The Sale contains invalid quantity evidence.']);
        }

        [$integer, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $integer = ltrim($integer, '0');
        $canonical = ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, 3, '0');
        if (strlen(strtok($canonical, '.')) > 11
            || bccomp($canonical, self::MAX_QUANTITY, 3) === 1
            || ($positive && bccomp($canonical, '0.000', 3) !== 1)) {
            throw ValidationException::withMessages(['sale' => 'The Sale contains invalid quantity evidence.']);
        }

        return $canonical;
    }
}

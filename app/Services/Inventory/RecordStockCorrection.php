<?php

namespace App\Services\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockCorrection
{
    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const MOVEMENT_ID_PATTERN = '/\A[1-9]\d*\z/D';

    private const MAX_QUANTITY = '99999999999.999';

    public function execute(
        ProductVariant $routeVariant,
        User $actor,
        mixed $correctedStock,
        mixed $expectedMovementId,
        mixed $reason,
    ): StockMovement {
        $variantId = filter_var($routeVariant->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $productId = filter_var($routeVariant->product_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $actorId = filter_var($actor->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $routeVariant->loadMissing('product:id,category_id');
        $categoryId = filter_var($routeVariant->product?->category_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($variantId === false || $productId === false || $categoryId === false) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The catalog hierarchy changed. Please retry.',
            ]);
        }
        if ($actorId === false) {
            throw ValidationException::withMessages(['actor' => 'An active Admin is required.']);
        }

        $target = $this->canonicalizeQuantity($correctedStock);
        $expectedId = $this->canonicalizeMovementId($expectedMovementId);
        $normalizedReason = $this->normalizeReason($reason);

        return DB::transaction(function () use (
            $variantId,
            $productId,
            $categoryId,
            $actorId,
            $target,
            $expectedId,
            $normalizedReason,
        ): StockMovement {
            $category = Category::query()->whereKey($categoryId)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $variant = ProductVariant::query()->whereKey($variantId)->lockForUpdate()->firstOrFail();

            if ((int) $product->category_id !== (int) $category->getKey()
                || (int) $variant->product_id !== (int) $product->getKey()) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The catalog hierarchy changed. Please retry.',
                ]);
            }

            if ($category->status !== Category::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Stock Correction requires an active category.',
                ]);
            }
            if ($product->status !== Product::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Stock Correction requires an active product.',
                ]);
            }
            if ($variant->status !== ProductVariant::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Stock Correction requires an active variant.',
                ]);
            }

            $currentActor = User::query()->whereKey($actorId)->first(['id', 'role', 'status']);
            if ($currentActor === null || ! $currentActor->isActive() || ! $currentActor->isAdmin()) {
                throw ValidationException::withMessages(['actor' => 'An active Admin is required.']);
            }

            $openingMovement = StockMovement::query()
                ->where('product_variant_id', $variant->getKey())
                ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
                ->lockForUpdate()
                ->first(['id']);
            if ($openingMovement === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Opening inventory must be completed before Stock Correction.',
                ]);
            }

            $latestMovement = StockMovement::query()
                ->where('product_variant_id', $variant->getKey())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first(['id']);
            if ($latestMovement === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Opening inventory must be completed before Stock Correction.',
                ]);
            }

            $this->validateQuantityMode($target, $variant->quantity_mode);
            $before = (string) $variant->current_stock;

            if (bccomp($target, $before, 3) === 0) {
                throw ValidationException::withMessages([
                    'corrected_stock' => 'No stock change is required.',
                ]);
            }

            if ($expectedId !== (int) $latestMovement->getKey()) {
                throw ValidationException::withMessages([
                    'corrected_stock' => 'Stock changed while this correction was being prepared. Review the latest stock and try again.',
                ]);
            }

            $change = bcsub($target, $before, 3);
            $variant->current_stock = $target;
            $variant->save();

            $movement = new StockMovement;
            $movement->product_variant_id = $variant->getKey();
            $movement->movement_type = StockMovement::TYPE_CORRECTION;
            $movement->quantity_before = $before;
            $movement->quantity_change = $change;
            $movement->quantity_after = $target;
            $movement->performed_by = $currentActor->getKey();
            $movement->sale_item_id = null;
            $movement->restock_item_id = null;
            $movement->reason = $normalizedReason;
            $movement->save();

            return $movement;
        });
    }

    private function canonicalizeQuantity(mixed $value): string
    {
        if (! is_string($value) || preg_match(self::QUANTITY_PATTERN, trim($value)) !== 1) {
            throw ValidationException::withMessages([
                'corrected_stock' => 'Enter a nonnegative ordinary decimal with up to three decimal places.',
            ]);
        }

        [$integer, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');
        $integer = ltrim($integer, '0');
        $canonical = ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, 3, '0');

        if (strlen(strtok($canonical, '.')) > 11 || bccomp($canonical, self::MAX_QUANTITY, 3) === 1) {
            throw ValidationException::withMessages(['corrected_stock' => 'The corrected stock is too large.']);
        }

        return $canonical;
    }

    private function canonicalizeMovementId(mixed $value): int
    {
        if (is_int($value)) {
            $movementId = $value;
        } elseif (is_string($value) && preg_match(self::MOVEMENT_ID_PATTERN, $value) === 1) {
            $movementId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        } else {
            $movementId = false;
        }

        if ($movementId === false || $movementId < 1) {
            throw ValidationException::withMessages([
                'expected_movement_id' => 'The inventory version is invalid. Refresh the form and try again.',
            ]);
        }

        return $movementId;
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

    private function validateQuantityMode(string $quantity, string $quantityMode): void
    {
        if (! in_array($quantityMode, ProductVariant::QUANTITY_MODES, true)) {
            throw ValidationException::withMessages([
                'corrected_stock' => 'The variant quantity mode is invalid.',
            ]);
        }
        if ($quantityMode === 'whole' && ! str_ends_with($quantity, '.000')) {
            throw ValidationException::withMessages([
                'corrected_stock' => 'The corrected stock must be a whole number for this variant.',
            ]);
        }
    }
}

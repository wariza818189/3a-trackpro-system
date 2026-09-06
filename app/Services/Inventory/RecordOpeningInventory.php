<?php

namespace App\Services\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestockItem;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordOpeningInventory
{
    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    public function execute(
        ProductVariant $routeVariant,
        User $actor,
        string $openingQuantity,
        string $reason,
    ): StockMovement {
        $routeVariant->loadMissing('product:id,category_id');
        $variantId = (int) $routeVariant->getKey();
        $productId = (int) $routeVariant->product_id;
        $categoryId = $routeVariant->product?->category_id;
        $normalizedReason = $this->normalizeReason($reason);

        if ($categoryId === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The catalog hierarchy changed. Please retry.',
            ]);
        }

        return DB::transaction(function () use (
            $variantId,
            $productId,
            $categoryId,
            $actor,
            $openingQuantity,
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
                    'product_variant_id' => 'Opening inventory requires an active category.',
                ]);
            }
            if ($product->status !== Product::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Opening inventory requires an active product.',
                ]);
            }
            if ($variant->status !== ProductVariant::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Opening inventory requires an active variant.',
                ]);
            }

            $quantity = $this->canonicalizeQuantity($openingQuantity, $variant->quantity_mode);

            $openingMovement = StockMovement::query()
                ->where('product_variant_id', $variant->getKey())
                ->where('movement_type', StockMovement::TYPE_INITIAL_STOCK)
                ->lockForUpdate()
                ->first(['id']);
            if ($openingMovement !== null) {
                throw ValidationException::withMessages([
                    'opening_quantity' => 'Opening inventory has already been recorded for this variant.',
                ]);
            }

            $otherMovement = StockMovement::query()
                ->where('product_variant_id', $variant->getKey())
                ->where('movement_type', '<>', StockMovement::TYPE_INITIAL_STOCK)
                ->lockForUpdate()
                ->first(['id']);
            if ($otherMovement !== null) {
                throw ValidationException::withMessages([
                    'opening_quantity' => 'Opening inventory is unavailable because stock activity already exists.',
                ]);
            }

            $saleItem = SaleItem::query()
                ->where('product_variant_id', $variant->getKey())
                ->lockForUpdate()
                ->first(['id']);
            if ($saleItem !== null) {
                throw ValidationException::withMessages([
                    'opening_quantity' => 'Opening inventory is unavailable because sale history already exists.',
                ]);
            }

            $restockItem = RestockItem::query()
                ->where('product_variant_id', $variant->getKey())
                ->lockForUpdate()
                ->first(['id']);
            if ($restockItem !== null) {
                throw ValidationException::withMessages([
                    'opening_quantity' => 'Opening inventory is unavailable because restock history already exists.',
                ]);
            }

            if ((string) $variant->current_stock !== '0.000') {
                throw ValidationException::withMessages([
                    'opening_quantity' => 'Opening inventory requires a zero starting stock balance.',
                ]);
            }

            $variant->current_stock = $quantity;
            $variant->save();

            $movement = new StockMovement;
            $movement->product_variant_id = $variant->getKey();
            $movement->movement_type = StockMovement::TYPE_INITIAL_STOCK;
            $movement->quantity_before = '0.000';
            $movement->quantity_change = $quantity;
            $movement->quantity_after = $quantity;
            $movement->performed_by = $actor->getKey();
            $movement->sale_item_id = null;
            $movement->restock_item_id = null;
            $movement->reason = $normalizedReason;
            $movement->save();

            return $movement;
        });
    }

    private function canonicalizeQuantity(string $quantity, string $quantityMode): string
    {
        $quantity = trim($quantity);

        if (preg_match(self::QUANTITY_PATTERN, $quantity) !== 1) {
            throw ValidationException::withMessages([
                'opening_quantity' => 'Enter a nonnegative ordinary decimal with up to three decimal places.',
            ]);
        }

        [$integer, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($fraction, 3, '0');

        if (strlen($integer) > 11) {
            throw ValidationException::withMessages([
                'opening_quantity' => 'The opening quantity is too large.',
            ]);
        }

        if ($quantityMode === 'whole' && $fraction !== '000') {
            throw ValidationException::withMessages([
                'opening_quantity' => 'The opening quantity must be a whole number for this variant.',
            ]);
        }

        if (! in_array($quantityMode, ProductVariant::QUANTITY_MODES, true)) {
            throw ValidationException::withMessages([
                'opening_quantity' => 'The variant quantity mode is invalid.',
            ]);
        }

        return $integer.'.'.$fraction;
    }

    private function normalizeReason(string $reason): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($reason));

        if ($normalized === null || $normalized === '') {
            throw ValidationException::withMessages(['reason' => 'A meaningful reason is required.']);
        }

        if (mb_strlen($normalized) > 1000) {
            throw ValidationException::withMessages(['reason' => 'The reason must not exceed 1000 characters.']);
        }

        return $normalized;
    }
}

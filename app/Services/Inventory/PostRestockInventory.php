<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restock;
use App\Models\RestockItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PostRestockInventory
{
    private const MAX_QUANTITY = '99999999999.999';

    /**
     * @param  EloquentCollection<int, ProductVariant>  $variants
     * @param  EloquentCollection<int, Product>  $products
     * @param  array<int, array{index: int, quantity: string, unit_cost: string, purchase_order_item_id?: int|null}>  $items
     * @return Collection<int, RestockItem>
     */
    public function post(
        Restock $restock,
        int $actorId,
        EloquentCollection $variants,
        EloquentCollection $products,
        array $items,
    ): Collection {
        $createdItems = collect();

        foreach ($variants as $variant) {
            $itemData = $items[(int) $variant->getKey()];
            $index = $itemData['index'];
            $this->validateQuantityMode($itemData['quantity'], (string) $variant->quantity_mode, $index);

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
            $item->purchase_order_item_id = $itemData['purchase_order_item_id'] ?? null;
            $item->product_name_snapshot = $product->name;
            $item->size_snapshot = $variant->size;
            $item->type_series_snapshot = $variant->type_series;
            $item->thickness_snapshot = $variant->thickness;
            $item->unit_snapshot = $variant->unit;
            $item->quantity = $itemData['quantity'];
            $item->unit_cost = $itemData['unit_cost'];
            $item->line_total = bcadd(bcmul($itemData['quantity'], $itemData['unit_cost'], 5), '0.005', 2);
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
            $movement->performed_by = $actorId;
            $movement->sale_item_id = null;
            $movement->restock_item_id = $item->getKey();
            $movement->reason = null;
            $movement->save();

            $item->setRelation('stockMovement', $movement);
            $createdItems->push($item);
        }

        return $createdItems;
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
}

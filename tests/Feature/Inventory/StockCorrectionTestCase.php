<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Tests\Feature\Catalog\CatalogTestCase;

abstract class StockCorrectionTestCase extends CatalogTestCase
{
    protected function initialize(ProductVariant $variant, User $actor): StockMovement
    {
        $stock = (string) $variant->current_stock;

        return StockMovement::query()->create([
            'product_variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_INITIAL_STOCK,
            'quantity_before' => '0.000',
            'quantity_change' => $stock,
            'quantity_after' => $stock,
            'performed_by' => $actor->id,
            'sale_item_id' => null,
            'restock_item_id' => null,
            'reason' => 'Test opening count',
        ]);
    }

    /** @return array<string, mixed> */
    protected function payload(StockMovement $latestMovement, array $overrides = []): array
    {
        return array_replace([
            'corrected_stock' => '7',
            'expected_movement_id' => (string) $latestMovement->id,
            'reason' => 'Physical count discrepancy',
        ], $overrides);
    }

    protected function correctionCount(): int
    {
        return StockMovement::query()->where('movement_type', StockMovement::TYPE_CORRECTION)->count();
    }
}

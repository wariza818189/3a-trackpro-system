<?php

namespace App\Queries\Inventory;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class StockMovementQuery
{
    /** @return LengthAwarePaginator<int, StockMovement> */
    public function history(): LengthAwarePaginator
    {
        return $this->base()->paginate(20);
    }

    /** @return Collection<int, StockMovement> */
    public function recent(): Collection
    {
        return $this->base()->limit(5)->get();
    }

    /** @return Builder<StockMovement> */
    private function base(): Builder
    {
        return StockMovement::query()
            ->with([
                'variant:id,product_id,size,type_series,thickness,unit',
                'variant.product:id,name',
                'performedBy:id,name',
                'saleItem:id,sale_id',
                'saleItem.sale:id',
                'restockItem:id,restock_id',
                'restockItem.restock:id,purchase_order_id',
            ])
            ->latest('created_at')
            ->latest('id');
    }
}

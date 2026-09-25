<?php

namespace App\Queries\Reports;

use App\Models\RestockItem;
use Illuminate\Support\Collection;

final class RestockingReportQuery
{
    /** @return Collection<int, RestockItem> */
    public function get(): Collection
    {
        return RestockItem::query()
            ->select([
                'restock_items.id', 'restock_items.restock_id',
                'restock_items.product_name_snapshot', 'restock_items.size_snapshot',
                'restock_items.type_series_snapshot', 'restock_items.thickness_snapshot',
                'restock_items.unit_snapshot', 'restock_items.quantity',
                'restock_items.unit_cost', 'restock_items.line_total',
            ])
            ->join('restocks', 'restocks.id', '=', 'restock_items.restock_id')
            ->with([
                'restock' => fn ($query) => $query->select(['id', 'purchase_order_id', 'recorded_by', 'created_at']),
                'restock.recordedBy' => fn ($query) => $query->select(['id', 'name']),
                'restock.purchaseOrder' => fn ($query) => $query->select(['id', 'supplier_name']),
            ])
            ->orderByDesc('restocks.created_at')
            ->orderByDesc('restocks.id')
            ->orderByDesc('restock_items.id')
            ->get();
    }
}

<?php

namespace App\Queries\Reports;

use App\Models\RestockDamageItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class DamagedItemsReportQuery
{
    /** @return Collection<int, RestockDamageItem> */
    public function get(string $supplier = '', string $itemSearch = '', ?int $poId = null): Collection
    {
        $escapedSupplier = self::escapeLike($supplier);
        $escapedItem = self::escapeLike($itemSearch);

        return RestockDamageItem::query()
            ->select([
                'restock_damage_items.id', 'restock_damage_items.restock_id',
                'restock_damage_items.product_name_snapshot', 'restock_damage_items.size_snapshot',
                'restock_damage_items.type_series_snapshot', 'restock_damage_items.thickness_snapshot',
                'restock_damage_items.unit_snapshot', 'restock_damage_items.damaged_quantity',
                'restock_damage_items.damage_note',
            ])
            ->join('restocks', 'restocks.id', '=', 'restock_damage_items.restock_id')
            ->when($poId !== null, fn (Builder $query) => $query->where('restocks.purchase_order_id', $poId))
            ->when($escapedSupplier !== '', fn (Builder $query) => $query->whereHas(
                'restock.purchaseOrder',
                fn (Builder $order) => $order->whereRaw("supplier_name LIKE ? ESCAPE '!'", ["%{$escapedSupplier}%"]),
            ))
            ->when($escapedItem !== '', function (Builder $query) use ($escapedItem): void {
                $query->where(function (Builder $query) use ($escapedItem): void {
                    foreach (['product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot'] as $column) {
                        $query->orWhereRaw("restock_damage_items.{$column} LIKE ? ESCAPE '!'", ["%{$escapedItem}%"]);
                    }
                });
            })
            ->with([
                'restock' => fn ($query) => $query->select(['id', 'purchase_order_id', 'recorded_by', 'created_at']),
                'restock.recordedBy' => fn ($query) => $query->select(['id', 'name']),
                'restock.purchaseOrder' => fn ($query) => $query->select(['id', 'supplier_name']),
            ])
            ->orderByDesc('restocks.created_at')
            ->orderByDesc('restocks.id')
            ->orderByDesc('restock_damage_items.id')
            ->get();
    }

    public function hasEvidence(): bool
    {
        return RestockDamageItem::query()->exists();
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}

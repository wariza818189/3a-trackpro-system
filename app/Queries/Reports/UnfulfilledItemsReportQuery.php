<?php

namespace App\Queries\Reports;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class UnfulfilledItemsReportQuery
{
    public function __construct(private readonly PurchaseOrderLineEvidence $evidence) {}

    /** @return Collection<int, array{item: PurchaseOrderItem, ordered: string, accepted: string, transferred: string, outstanding: string}> */
    public function get(string $supplier = '', string $itemSearch = '', ?string $status = null, ?int $poId = null): Collection
    {
        $escapedSupplier = self::escapeLike($supplier);
        $escapedItem = self::escapeLike($itemSearch);

        return PurchaseOrderItem::query()
            ->select([
                'purchase_order_items.id', 'purchase_order_items.purchase_order_id',
                'purchase_order_items.product_name_snapshot', 'purchase_order_items.size_snapshot',
                'purchase_order_items.type_series_snapshot', 'purchase_order_items.thickness_snapshot',
                'purchase_order_items.unit_snapshot', 'purchase_order_items.ordered_quantity',
            ])
            ->whereHas('purchaseOrder', function (Builder $query) use ($escapedSupplier, $status): void {
                $query->whereIn('status', PurchaseOrder::OPEN_STATUSES)
                    ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
                    ->when($escapedSupplier !== '', fn (Builder $query) => $query->whereRaw("supplier_name LIKE ? ESCAPE '!'", ["%{$escapedSupplier}%"]));
            })
            ->when($poId !== null, fn (Builder $query) => $query->where('purchase_order_id', $poId))
            ->when($escapedItem !== '', function (Builder $query) use ($escapedItem): void {
                $query->where(function (Builder $query) use ($escapedItem): void {
                    foreach (['product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot'] as $column) {
                        $query->orWhereRaw("{$column} LIKE ? ESCAPE '!'", ["%{$escapedItem}%"]);
                    }
                });
            })
            ->with([
                'purchaseOrder' => fn ($query) => $query->select(['id', 'parent_purchase_order_id', 'supplier_name', 'status', 'created_at']),
                'purchaseOrder.children' => fn ($query) => $query->select(['id', 'parent_purchase_order_id'])->orderBy('id'),
                'restockItems' => fn ($query) => $query->select(['id', 'purchase_order_item_id', 'quantity']),
                'outgoingTransfer' => fn ($query) => $query->select(['id', 'source_purchase_order_item_id', 'quantity']),
            ])
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->orderBy('purchase_orders.created_at')
            ->orderBy('purchase_orders.id')
            ->orderBy('purchase_order_items.id')
            ->get()
            ->map(function (PurchaseOrderItem $item): array {
                return ['item' => $item] + $this->evidence->calculate(
                    (string) $item->ordered_quantity,
                    $item->restockItems->pluck('quantity'),
                    (string) ($item->outgoingTransfer?->quantity ?? '0.000'),
                );
            })
            ->filter(fn (array $row): bool => bccomp($row['outstanding'], '0.000', 3) > 0)
            ->values();
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}

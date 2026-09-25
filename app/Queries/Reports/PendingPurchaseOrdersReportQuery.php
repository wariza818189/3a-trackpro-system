<?php

namespace App\Queries\Reports;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;

class PendingPurchaseOrdersReportQuery
{
    public function __construct(private readonly PurchaseOrderLineEvidence $evidence) {}

    /** @return Collection<int, array{order: PurchaseOrder, lines: Collection}> */
    public function get(string $supplier = '', ?string $status = null): Collection
    {
        $escapedSupplier = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $supplier);

        return PurchaseOrder::query()
            ->select(['id', 'parent_purchase_order_id', 'supplier_name', 'status', 'created_at'])
            ->whereIn('status', PurchaseOrder::OPEN_STATUSES)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($supplier !== '', fn ($query) => $query->whereRaw("supplier_name LIKE ? ESCAPE '!'", ["%{$escapedSupplier}%"]))
            ->with([
                'children' => fn ($query) => $query->select(['id', 'parent_purchase_order_id'])->orderBy('id'),
                'items' => fn ($query) => $query
                    ->select([
                        'id', 'purchase_order_id', 'product_name_snapshot', 'size_snapshot',
                        'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot', 'ordered_quantity',
                    ])
                    ->orderBy('id'),
                'items.restockItems' => fn ($query) => $query->select(['id', 'purchase_order_item_id', 'quantity']),
                'items.outgoingTransfer' => fn ($query) => $query->select(['id', 'source_purchase_order_item_id', 'quantity']),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (PurchaseOrder $order): array {
                $lines = $order->items->map(function (PurchaseOrderItem $item): array {
                    $quantities = $this->evidence->calculate(
                        (string) $item->ordered_quantity,
                        $item->restockItems->pluck('quantity'),
                        (string) ($item->outgoingTransfer?->quantity ?? '0.000'),
                    );

                    return ['item' => $item] + $quantities;
                })->filter(fn (array $line): bool => bccomp($line['outstanding'], '0.000', 3) > 0)->values();

                return ['order' => $order, 'lines' => $lines];
            })
            ->filter(fn (array $row): bool => $row['lines']->isNotEmpty())
            ->values();
    }
}

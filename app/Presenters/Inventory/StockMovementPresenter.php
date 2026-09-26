<?php

namespace App\Presenters\Inventory;

use App\Models\StockMovement;

final class StockMovementPresenter
{
    /** @return array<string, int|string|null> */
    public function present(StockMovement $movement): array
    {
        $label = match ($movement->movement_type) {
            StockMovement::TYPE_INITIAL_STOCK => 'Opening Inventory',
            StockMovement::TYPE_RESTOCK => 'Stock In',
            StockMovement::TYPE_SALE => 'Sale',
            StockMovement::TYPE_CORRECTION => 'Stock Correction',
            StockMovement::TYPE_SALE_VOID => 'Sale Void',
        };

        $reference = $label;
        if ($movement->movement_type === StockMovement::TYPE_RESTOCK) {
            $restock = $movement->restockItem->restock;
            $reference = $restock->restockNumber();
            if ($restock->purchase_order_id !== null) {
                $reference .= ' · PO #'.$restock->purchase_order_id;
            }
        } elseif (in_array($movement->movement_type, [StockMovement::TYPE_SALE, StockMovement::TYPE_SALE_VOID], true)) {
            $reference = $movement->saleItem->sale->receiptNumber();
            if ($movement->movement_type === StockMovement::TYPE_SALE_VOID) {
                $reference .= ' · Sale Void';
            }
        }

        $variant = $movement->variant;
        $identity = collect([$variant->size, $variant->type_series, $variant->thickness])
            ->filter(fn (?string $value): bool => $value !== null && $value !== '')
            ->join(' · ') ?: 'Standard';
        $change = bcadd((string) $movement->quantity_change, '0', 3);
        $date = $movement->created_at?->copy()->timezone('Asia/Manila');

        return [
            'id' => $movement->id,
            'label' => $label,
            'reference' => $reference,
            'product' => $variant->product->name,
            'variant' => $identity.' · '.$variant->unit,
            'actor' => $movement->performedBy->name,
            'change' => str_starts_with($change, '-') ? '−'.substr($change, 1) : '+'.$change,
            'before' => bcadd((string) $movement->quantity_before, '0', 3),
            'after' => bcadd((string) $movement->quantity_after, '0', 3),
            'reason' => $movement->reason,
            'date' => $date?->format('M j, Y g:i A') ?? '—',
            'datetime' => $date?->toAtomString(),
        ];
    }
}

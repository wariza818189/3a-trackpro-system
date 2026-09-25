<?php

namespace App\Queries\Reports;

final class PurchaseOrderLineEvidence
{
    /** @param iterable<string> $acceptedQuantities
     * @return array{ordered: string, accepted: string, transferred: string, outstanding: string}
     */
    public function calculate(string $orderedQuantity, iterable $acceptedQuantities, string $transferredQuantity): array
    {
        $ordered = bcadd('0.000', $orderedQuantity, 3);
        $accepted = '0.000';
        foreach ($acceptedQuantities as $quantity) {
            $accepted = bcadd($accepted, (string) $quantity, 3);
        }

        $transferred = bcadd('0.000', $transferredQuantity, 3);
        $remaining = bcsub(bcsub($ordered, $accepted, 3), $transferred, 3);
        $outstanding = bccomp($remaining, '0.000', 3) > 0 ? $remaining : '0.000';

        return compact('ordered', 'accepted', 'transferred', 'outstanding');
    }
}

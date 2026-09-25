<?php

namespace App\Queries\Reports;

use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ProductSalesReportQuery
{
    /** @return Collection<int, array<string, int|string>> */
    public function get(CarbonImmutable $from, CarbonImmutable $end): Collection
    {
        $groups = [];

        $items = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.created_at', '>=', $from)
            ->where('sales.created_at', '<', $end)
            ->select([
                'sale_items.product_variant_id',
                'sale_items.product_name_snapshot',
                'sale_items.size_snapshot',
                'sale_items.type_series_snapshot',
                'sale_items.thickness_snapshot',
                'sale_items.unit_snapshot',
                'sale_items.quantity',
                'sale_items.line_total',
            ])
            ->get();

        foreach ($items as $item) {
            $identity = [
                'product_variant_id' => (int) $item->product_variant_id,
                'product_name_snapshot' => (string) $item->product_name_snapshot,
                'size_snapshot' => (string) $item->size_snapshot,
                'type_series_snapshot' => (string) $item->type_series_snapshot,
                'thickness_snapshot' => (string) $item->thickness_snapshot,
                'unit_snapshot' => (string) $item->unit_snapshot,
            ];
            $key = json_encode(array_values($identity), JSON_THROW_ON_ERROR);
            if (! isset($groups[$key])) {
                $groups[$key] = $identity + ['quantity' => '0.000', 'sales_amount' => '0.00'];
            }

            $groups[$key]['quantity'] = bcadd($groups[$key]['quantity'], (string) $item->quantity, 3);
            $groups[$key]['sales_amount'] = bcadd($groups[$key]['sales_amount'], (string) $item->line_total, 2);
        }

        $rows = array_values($groups);
        usort($rows, static function (array $left, array $right): int {
            foreach (['product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot'] as $field) {
                $comparison = strcmp($left[$field], $right[$field]);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return $left['product_variant_id'] <=> $right['product_variant_id'];
        });

        return collect($rows);
    }
}

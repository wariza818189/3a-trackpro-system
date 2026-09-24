<?php

namespace App\Queries\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class PurchaseOrderCoverageQuery
{
    public const AGGREGATE_ALIAS = 'open_purchase_order_coverage';

    public function aggregate(): QueryBuilder
    {
        return DB::table('purchase_order_items')
            ->leftJoinSub(
                DB::table('restock_items')
                    ->select('purchase_order_item_id')
                    ->selectRaw('SUM(quantity) as accepted_quantity')
                    ->whereNotNull('purchase_order_item_id')
                    ->groupBy('purchase_order_item_id'),
                'accepted_receipts',
                'accepted_receipts.purchase_order_item_id',
                '=',
                'purchase_order_items.id',
            )
            ->join(
                'purchase_orders',
                'purchase_orders.id',
                '=',
                'purchase_order_items.purchase_order_id',
            )
            ->whereIn('purchase_orders.status', PurchaseOrder::OPEN_STATUSES)
            ->groupBy('purchase_order_items.product_variant_id')
            ->select('purchase_order_items.product_variant_id')
            ->selectRaw('ROUND(SUM(CASE WHEN purchase_order_items.ordered_quantity > COALESCE(accepted_receipts.accepted_quantity, 0) THEN purchase_order_items.ordered_quantity - COALESCE(accepted_receipts.accepted_quantity, 0) ELSE 0 END), 3) as open_coverage_quantity');
    }

    /** @param EloquentBuilder<ProductVariant> $variants
     * @return EloquentBuilder<ProductVariant>
     */
    public function applyTo(EloquentBuilder $variants): EloquentBuilder
    {
        $alias = self::AGGREGATE_ALIAS;

        return $variants
            ->leftJoinSub(
                $this->aggregate(),
                $alias,
                fn (JoinClause $join): JoinClause => $join->on(
                    "{$alias}.product_variant_id",
                    '=',
                    'product_variants.id',
                ),
            )
            ->addSelect(DB::raw(
                "COALESCE({$alias}.open_coverage_quantity, 0) as open_coverage_quantity",
            ))
            ->withCasts(['open_coverage_quantity' => 'decimal:3']);
    }
}

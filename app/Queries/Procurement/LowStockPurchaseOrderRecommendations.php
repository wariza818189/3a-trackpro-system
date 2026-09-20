<?php

namespace App\Queries\Procurement;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

class LowStockPurchaseOrderRecommendations
{
    private const COVERAGE_EXPRESSION = 'COALESCE(open_purchase_order_coverage.open_coverage_quantity, 0)';

    public function __construct(
        private readonly ProcurementVariantCatalogQuery $catalog,
        private readonly PurchaseOrderCoverageQuery $coverage,
    ) {}

    /** @return Builder<ProductVariant> */
    public function all(): Builder
    {
        return $this->order(
            $this->base()->orderByRaw(self::COVERAGE_EXPRESSION.' > 0'),
        );
    }

    /** @return Builder<ProductVariant> */
    public function uncovered(): Builder
    {
        return $this->order(
            $this->base()->whereRaw(self::COVERAGE_EXPRESSION.' = 0'),
        );
    }

    /** @return Builder<ProductVariant> */
    public function covered(): Builder
    {
        return $this->order(
            $this->base()->whereRaw(self::COVERAGE_EXPRESSION.' > 0'),
        );
    }

    /** @return Builder<ProductVariant> */
    private function base(): Builder
    {
        $query = $this->catalog->query()
            ->select([
                'product_variants.id',
                'product_variants.product_id',
                'product_variants.size',
                'product_variants.type_series',
                'product_variants.thickness',
                'product_variants.unit',
                'product_variants.quantity_mode',
                'product_variants.current_stock',
                'product_variants.low_stock_threshold',
            ])
            ->with([
                'product:id,category_id,name',
                'product.category:id,name',
            ])
            ->lowStock();

        $this->coverage->applyTo($query);

        return $query->selectRaw(
            'CASE WHEN '.self::COVERAGE_EXPRESSION." > 0 THEN 'covered' ELSE 'uncovered' END as coverage_state",
        );
    }

    /** @param Builder<ProductVariant> $query
     * @return Builder<ProductVariant>
     */
    private function order(Builder $query): Builder
    {
        return $query
            ->orderBy('product_variants.current_stock')
            ->orderBy('product_variants.product_id')
            ->orderBy('product_variants.size')
            ->orderBy('product_variants.type_series')
            ->orderBy('product_variants.thickness')
            ->orderBy('product_variants.unit')
            ->orderBy('product_variants.id');
    }
}

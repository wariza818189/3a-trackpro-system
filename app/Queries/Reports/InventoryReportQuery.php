<?php

namespace App\Queries\Reports;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

final class InventoryReportQuery
{
    /** @return Collection<int, ProductVariant> */
    public function get(): Collection
    {
        return ProductVariant::query()
            ->select([
                'product_variants.id', 'product_variants.product_id', 'product_variants.size',
                'product_variants.type_series', 'product_variants.thickness', 'product_variants.unit',
                'product_variants.quantity_mode', 'product_variants.status',
                'product_variants.current_stock', 'product_variants.low_stock_threshold',
            ])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->with(['product:id,category_id,name,status', 'product.category:id,name,status'])
            ->orderBy('categories.name')
            ->orderBy('products.name')
            ->orderBy('product_variants.size')
            ->orderBy('product_variants.type_series')
            ->orderBy('product_variants.thickness')
            ->orderBy('product_variants.unit')
            ->orderBy('product_variants.id')
            ->get();
    }
}

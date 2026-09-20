<?php

namespace App\Queries\Procurement;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

class ProcurementVariantCatalogQuery
{
    /** @return Builder<ProductVariant> */
    public function query(): Builder
    {
        return ProductVariant::query()
            ->inActiveHierarchy()
            ->initialized();
    }
}

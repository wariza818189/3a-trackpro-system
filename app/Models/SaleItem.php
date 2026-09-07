<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleItem extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'product_name_snapshot',
        'size_snapshot',
        'type_series_snapshot',
        'thickness_snapshot',
        'unit_snapshot',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function saleMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'sale_item_id')
            ->where('movement_type', StockMovement::TYPE_SALE);
    }
}

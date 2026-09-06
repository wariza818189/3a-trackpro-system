<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestockItem extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $fillable = [
        'restock_id',
        'product_variant_id',
        'product_name_snapshot',
        'size_snapshot',
        'type_series_snapshot',
        'thickness_snapshot',
        'unit_snapshot',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function restock(): BelongsTo
    {
        return $this->belongsTo(Restock::class, 'restock_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'restock_item_id');
    }

    public function stockMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'restock_item_id');
    }
}

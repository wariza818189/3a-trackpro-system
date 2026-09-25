<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockDamageItem extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $fillable = [
        'restock_id',
        'purchase_order_item_id',
        'product_variant_id',
        'product_name_snapshot',
        'size_snapshot',
        'type_series_snapshot',
        'thickness_snapshot',
        'unit_snapshot',
        'damaged_quantity',
        'damage_note',
    ];

    protected function casts(): array
    {
        return [
            'damaged_quantity' => 'decimal:3',
        ];
    }

    public function restock(): BelongsTo
    {
        return $this->belongsTo(Restock::class, 'restock_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

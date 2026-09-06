<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use ImmutableRecord;

    public const TYPE_INITIAL_STOCK = 'INITIAL_STOCK';

    public const TYPE_RESTOCK = 'RESTOCK';

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_variant_id',
        'movement_type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'performed_by',
        'sale_item_id',
        'restock_item_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before' => 'decimal:3',
            'quantity_change' => 'decimal:3',
            'quantity_after' => 'decimal:3',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function restockItem(): BelongsTo
    {
        return $this->belongsTo(RestockItem::class, 'restock_item_id');
    }
}

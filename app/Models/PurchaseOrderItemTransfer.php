<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItemTransfer extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $fillable = [
        'source_purchase_order_item_id',
        'target_purchase_order_item_id',
        'quantity',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'source_purchase_order_item_id');
    }

    public function targetItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'target_purchase_order_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

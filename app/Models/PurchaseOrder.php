<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED_WITH_REMAINDER = 'closed_with_remainder';

    protected $fillable = [
        'supplier_name',
        'notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_purchase_order_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_purchase_order_id');
    }
}

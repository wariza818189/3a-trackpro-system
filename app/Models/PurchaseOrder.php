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

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PARTIALLY_RECEIVED,
    ];

    protected $fillable = [
        'supplier_name',
        'notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function isEditable(): bool
    {
        // The update service also checks accepted receiving evidence under lock.
        return $this->status === self::STATUS_PENDING;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function restocks(): HasMany
    {
        return $this->hasMany(Restock::class, 'purchase_order_id');
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

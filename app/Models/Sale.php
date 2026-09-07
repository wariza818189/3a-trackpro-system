<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use ImmutableRecord;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const UPDATED_AT = null;

    protected $fillable = [
        'checkout_token',
        'recorded_by',
        'status',
        'total_amount',
        'cash_received',
        'change_amount',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function receiptNumber(): string
    {
        if ($this->getKey() === null) {
            throw new \LogicException('A receipt number requires a persisted sale ID.');
        }

        return 'TRX-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restock extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $fillable = [
        'submission_token',
        'recorded_by',
        'reference_text',
        'notes',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:2',
        ];
    }

    public function restockNumber(): string
    {
        if ($this->getKey() === null) {
            throw new \LogicException('A restock number requires a persisted restock ID.');
        }

        return 'RST-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestockItem::class, 'restock_id');
    }
}

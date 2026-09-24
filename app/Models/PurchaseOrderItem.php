<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_variant_id',
        'product_name_snapshot',
        'size_snapshot',
        'type_series_snapshot',
        'thickness_snapshot',
        'unit_snapshot',
        'ordered_quantity',
        'expected_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'expected_unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function restockItems(): HasMany
    {
        return $this->hasMany(RestockItem::class, 'purchase_order_item_id');
    }

    public function acceptedQuantity(): string
    {
        $accepted = '0.000';
        foreach ($this->restockItems()->pluck('quantity') as $quantity) {
            $accepted = bcadd($accepted, (string) $quantity, 3);
        }

        return $accepted;
    }

    public function outstandingQuantity(): string
    {
        $outstanding = bcsub((string) $this->ordered_quantity, $this->acceptedQuantity(), 3);

        return bccomp($outstanding, '0.000', 3) > 0 ? $outstanding : '0.000';
    }
}

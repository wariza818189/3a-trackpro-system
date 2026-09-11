<?php

namespace App\Models;

use App\Models\Concerns\NormalizesCatalogFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ProductVariant extends Model
{
    use NormalizesCatalogFields;

    protected $fillable = [
        'product_id',
        'size',
        'type_series',
        'thickness',
        'unit',
        'quantity_mode',
        'cost_price',
        'selling_price',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'current_stock' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
        ];
    }

    public const SUPPORTED_UNITS = ['piece', 'sheet', 'roll', 'm', 'kg'];

    public const QUANTITY_MODES = ['whole', 'fractional'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $attributes = [
        'size' => '',
        'type_series' => '',
        'thickness' => '',
        'quantity_mode' => 'whole',
        'current_stock' => '0.000',
        'low_stock_threshold' => '0.000',
        'status' => 'active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInActiveHierarchy(Builder $query): Builder
    {
        return $query->active()->whereHas(
            'product',
            fn (Builder $product): Builder => $product->inActiveHierarchy(),
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_variant_id');
    }

    public function restockItems(): HasMany
    {
        return $this->hasMany(RestockItem::class, 'product_variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_variant_id');
    }

    public function openingInventoryMovements(): HasMany
    {
        return $this->stockMovements()->where('movement_type', StockMovement::TYPE_INITIAL_STOCK);
    }

    public function displayCurrentStock(): string
    {
        return $this->displayQuantity((string) $this->current_stock);
    }

    public function displayLowStockThreshold(): string
    {
        return $this->displayQuantity((string) $this->low_stock_threshold);
    }

    public function displayQuantity(string $quantity): string
    {
        if (preg_match('/\A(\d+)\.(\d{3})\z/D', $quantity, $matches) !== 1) {
            throw new LogicException('A displayed inventory quantity must be a canonical three-decimal value.');
        }

        return match ($this->quantity_mode) {
            'whole' => $matches[2] === '000'
                ? $matches[1]
                : throw new LogicException('A whole-quantity Variant cannot display a fractional value.'),
            'fractional' => $quantity,
            default => throw new LogicException('A displayed inventory quantity has an unsupported quantity mode.'),
        };
    }
}

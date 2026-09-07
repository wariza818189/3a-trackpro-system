<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStockCorrectionRequest extends FormRequest
{
    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const MOVEMENT_ID_PATTERN = '/\A[1-9]\d*\z/D';

    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'corrected_stock' => ['bail', 'required', 'string', 'regex:'.self::QUANTITY_PATTERN],
            'expected_movement_id' => ['bail', 'required', 'string', 'regex:'.self::MOVEMENT_ID_PATTERN],
            'reason' => ['bail', 'required', 'string', 'max:1000'],
            'product_variant_id' => ['prohibited'],
            'current_stock' => ['prohibited'],
            'quantity_before' => ['prohibited'],
            'quantity_change' => ['prohibited'],
            'quantity_after' => ['prohibited'],
            'movement_type' => ['prohibited'],
            'performed_by' => ['prohibited'],
            'sale_item_id' => ['prohibited'],
            'restock_item_id' => ['prohibited'],
            'cost_price' => ['prohibited'],
            'selling_price' => ['prohibited'],
            'low_stock_threshold' => ['prohibited'],
            'size' => ['prohibited'],
            'type_series' => ['prohibited'],
            'thickness' => ['prohibited'],
            'unit' => ['prohibited'],
            'quantity_mode' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        $quantity = $this->input('corrected_stock');
        $reason = $this->input('reason');

        if (is_string($quantity)) {
            $normalized['corrected_stock'] = trim($quantity);
        }

        if (is_string($reason)) {
            $normalizedReason = preg_replace('/\s+/u', ' ', trim($reason));
            if (is_string($normalizedReason)) {
                $normalized['reason'] = $normalizedReason;
            }
        }

        $this->merge($normalized);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('corrected_stock')) {
                return;
            }

            $quantity = $this->input('corrected_stock');
            if (! is_string($quantity) || preg_match(self::QUANTITY_PATTERN, $quantity) !== 1) {
                return;
            }

            [$integer, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
            $integer = ltrim($integer, '0');
            $integer = $integer === '' ? '0' : $integer;
            $fraction = str_pad($fraction, 3, '0');

            if (strlen($integer) > 11) {
                $validator->errors()->add('corrected_stock', 'The corrected stock is too large.');

                return;
            }

            $variant = $this->route('productVariant');
            if ($variant instanceof ProductVariant
                && $variant->quantity_mode === 'whole'
                && $fraction !== '000') {
                $validator->errors()->add(
                    'corrected_stock',
                    'The corrected stock must be a whole number for this variant.',
                );
            }
        }];
    }
}

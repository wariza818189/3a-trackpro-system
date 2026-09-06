<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOpeningInventoryRequest extends FormRequest
{
    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'opening_quantity' => ['bail', 'required', 'string', 'regex:'.self::QUANTITY_PATTERN],
            'reason' => ['bail', 'required', 'string', 'max:1000'],
            'product_variant_id' => ['prohibited'],
            'performed_by' => ['prohibited'],
            'movement_type' => ['prohibited'],
            'quantity_before' => ['prohibited'],
            'quantity_change' => ['prohibited'],
            'quantity_after' => ['prohibited'],
            'sale_item_id' => ['prohibited'],
            'restock_item_id' => ['prohibited'],
            'current_stock' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        $quantity = $this->input('opening_quantity');
        $reason = $this->input('reason');

        if (is_string($quantity)) {
            $normalized['opening_quantity'] = trim($quantity);
        }

        if (is_string($reason)) {
            $normalizedReason = preg_replace('/\s+/u', ' ', trim($reason));
            if ($normalizedReason !== null) {
                $normalized['reason'] = $normalizedReason;
            }
        }

        $this->merge($normalized);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('opening_quantity')) {
                return;
            }

            $quantity = $this->input('opening_quantity');
            if (! is_string($quantity) || preg_match(self::QUANTITY_PATTERN, $quantity) !== 1) {
                return;
            }

            [$integer, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
            $integer = ltrim($integer, '0');
            $integer = $integer === '' ? '0' : $integer;
            $fraction = str_pad($fraction, 3, '0');

            if (strlen($integer) > 11) {
                $validator->errors()->add('opening_quantity', 'The opening quantity is too large.');

                return;
            }

            $variant = $this->route('productVariant');
            if ($variant instanceof ProductVariant
                && $variant->quantity_mode === 'whole'
                && $fraction !== '000') {
                $validator->errors()->add(
                    'opening_quantity',
                    'The opening quantity must be a whole number for this variant.',
                );
            }
        }];
    }
}

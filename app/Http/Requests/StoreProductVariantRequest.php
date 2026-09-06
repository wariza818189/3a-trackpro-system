<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    public function rules(): array
    {
        return $this->variantRules();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeVariantInput();
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateWholeThreshold($validator),
            fn (Validator $validator) => $this->validateUniqueIdentity($validator),
        ];
    }

    /** @return array<string, list<mixed>> */
    protected function variantRules(): array
    {
        return [
            'size' => ['present', 'string', 'max:80'],
            'type_series' => ['present', 'string', 'max:80'],
            'thickness' => ['present', 'string', 'max:40'],
            'unit' => ['bail', 'required', 'string', 'max:30', Rule::in(ProductVariant::SUPPORTED_UNITS)],
            'quantity_mode' => ['bail', 'required', 'string', Rule::in(ProductVariant::QUANTITY_MODES)],
            'cost_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['bail', 'required', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'low_stock_threshold' => ['bail', 'required', 'decimal:0,3', 'min:0', 'max:99999999999.999'],
            'product_id' => ['prohibited'],
            'current_stock' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    protected function normalizeVariantInput(): void
    {
        $normalized = [];

        foreach (['size', 'type_series', 'thickness'] as $field) {
            $value = $this->input($field);
            if ($value === null || is_string($value)) {
                $normalized[$field] = ProductVariant::normalizeCatalogField($field, $value);
            }
        }

        $unit = $this->input('unit');
        if (is_string($unit)) {
            $normalized['unit'] = ProductVariant::normalizeCatalogField('unit', $unit);
        }

        if ($this->input('cost_price') === '') {
            $normalized['cost_price'] = null;
        }

        $this->merge($normalized);
    }

    protected function validateWholeThreshold(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['quantity_mode', 'low_stock_threshold'])
            || $this->input('quantity_mode') !== 'whole') {
            return;
        }

        $threshold = (string) $this->input('low_stock_threshold');
        if (preg_match('/\.\d*[1-9]\d*$/', $threshold) === 1) {
            $validator->errors()->add(
                'low_stock_threshold',
                'The low stock threshold must be a whole number for whole-quantity variants.',
            );
        }
    }

    protected function validateUniqueIdentity(Validator $validator): void
    {
        $identity = ['size', 'type_series', 'thickness', 'unit'];
        if ($validator->errors()->hasAny($identity)) {
            return;
        }

        $product = $this->route('product');
        if (! $product) {
            return;
        }

        $query = ProductVariant::query()->where('product_id', $product->getKey());
        foreach ($identity as $field) {
            $query->whereRaw("LOWER({$field}) = LOWER(?)", [$this->input($field)]);
        }

        if ($query->exists()) {
            $validator->errors()->add('size', 'This product already contains a variant with the same identity and unit.');
        }
    }
}

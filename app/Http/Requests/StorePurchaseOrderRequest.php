<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'submission_token' => ['bail', 'required', 'string', 'uuid', 'max:36'],
            'supplier_name' => ['bail', 'required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['bail', 'required', 'array', 'min:1', 'max:100'],
            'items.*' => ['bail', 'required', 'array:product_variant_id,ordered_quantity,expected_unit_cost'],
            'items.*.product_variant_id' => ['bail', 'required', 'integer', 'min:1', 'distinct:strict'],
            'items.*.ordered_quantity' => ['bail', 'required', 'string', 'regex:'.self::QUANTITY_PATTERN],
            'items.*.expected_unit_cost' => ['bail', 'required', 'string', 'regex:'.self::COST_PATTERN],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        $token = $this->input('submission_token');
        if (is_string($token)) {
            $normalized['submission_token'] = strtolower(trim($token));
        }

        foreach (['supplier_name', 'notes'] as $field) {
            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }

            $value = preg_replace('/[\p{Z}\s]+/u', ' ', $value);
            if (is_string($value)) {
                $value = trim($value);
                $normalized[$field] = $field === 'notes' && $value === '' ? null : $value;
            }
        }

        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['ordered_quantity', 'expected_unit_cost'] as $field) {
                    if (array_key_exists($field, $item) && is_string($item[$field])) {
                        $items[$index][$field] = trim($item[$field]);
                    }
                }
            }
            $normalized['items'] = $items;
        }

        $this->merge($normalized);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $topLevel = array_keys($this->all());
            sort($topLevel);
            $allowed = ['_token', 'items', 'notes', 'submission_token', 'supplier_name'];
            if (array_diff($topLevel, $allowed) !== []) {
                $validator->errors()->add('request', 'The Purchase Order submission contains unexpected fields.');
            }

            $items = $this->input('items');
            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $quantity = $item['ordered_quantity'] ?? null;
                if (is_string($quantity) && preg_match(self::QUANTITY_PATTERN, $quantity) === 1) {
                    $canonical = $this->canonicalUnsigned($quantity, 3);
                    if (strlen(strtok($canonical, '.')) > 11
                        || bccomp($canonical, '99999999999.999', 3) === 1) {
                        $validator->errors()->add("items.{$index}.ordered_quantity", 'The ordered quantity is too large.');
                    } elseif (bccomp($canonical, '0.000', 3) !== 1) {
                        $validator->errors()->add("items.{$index}.ordered_quantity", 'The ordered quantity must be greater than zero.');
                    }
                }

                $cost = $item['expected_unit_cost'] ?? null;
                if (is_string($cost) && preg_match(self::COST_PATTERN, $cost) === 1) {
                    $canonical = $this->canonicalUnsigned($cost, 2);
                    if (strlen(strtok($canonical, '.')) > 10
                        || bccomp($canonical, '9999999999.99', 2) === 1) {
                        $validator->errors()->add("items.{$index}.expected_unit_cost", 'The expected unit cost is too large.');
                    }
                }
            }
        }];
    }

    private function canonicalUnsigned(string $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, $scale, '0');
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRestockRequest extends FormRequest
{
    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'submission_token' => ['bail', 'required', 'string', 'uuid', 'max:36'],
            'reference_text' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['bail', 'required', 'array', 'min:1', 'max:100'],
            'items.*' => ['bail', 'required', 'array:product_variant_id,quantity,unit_cost'],
            'items.*.product_variant_id' => ['bail', 'required', 'integer', 'min:1', 'distinct:strict'],
            'items.*.quantity' => ['bail', 'required', 'string', 'regex:'.self::QUANTITY_PATTERN],
            'items.*.unit_cost' => ['bail', 'required', 'string', 'regex:'.self::COST_PATTERN],
            'recorded_by' => ['prohibited'],
            'total_cost' => ['prohibited'],
            'restock_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'items.*.restock_item_id' => ['prohibited'],
            'items.*.restock_id' => ['prohibited'],
            'items.*.product_name_snapshot' => ['prohibited'],
            'items.*.size_snapshot' => ['prohibited'],
            'items.*.type_series_snapshot' => ['prohibited'],
            'items.*.thickness_snapshot' => ['prohibited'],
            'items.*.unit_snapshot' => ['prohibited'],
            'items.*.line_total' => ['prohibited'],
            'items.*.current_stock' => ['prohibited'],
            'items.*.cost_price' => ['prohibited'],
            'items.*.quantity_before' => ['prohibited'],
            'items.*.quantity_change' => ['prohibited'],
            'items.*.quantity_after' => ['prohibited'],
            'items.*.performed_by' => ['prohibited'],
            'items.*.movement_type' => ['prohibited'],
            'items.*.sale_item_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        $token = $this->input('submission_token');
        if (is_string($token)) {
            $normalized['submission_token'] = strtolower(trim($token));
        }

        foreach (['reference_text', 'notes'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = preg_replace('/\s+/u', ' ', trim($value));
                if (is_string($value)) {
                    $normalized[$field] = $value === '' ? null : $value;
                }
            }
        }

        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['quantity', 'unit_cost'] as $field) {
                    if (isset($item[$field]) && is_string($item[$field])) {
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
            $items = $this->input('items');
            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $quantity = $item['quantity'] ?? null;
                if (is_string($quantity) && preg_match(self::QUANTITY_PATTERN, $quantity) === 1) {
                    $canonical = $this->canonicalUnsigned($quantity, 3);
                    if (strlen(strtok($canonical, '.')) > 11) {
                        $validator->errors()->add("items.{$index}.quantity", 'The received quantity is too large.');
                    } elseif (bccomp($canonical, '0.000', 3) !== 1) {
                        $validator->errors()->add("items.{$index}.quantity", 'The received quantity must be greater than zero.');
                    }
                }

                $cost = $item['unit_cost'] ?? null;
                if (is_string($cost) && preg_match(self::COST_PATTERN, $cost) === 1) {
                    $canonical = $this->canonicalUnsigned($cost, 2);
                    if (strlen(strtok($canonical, '.')) > 10) {
                        $validator->errors()->add("items.{$index}.unit_cost", 'The unit cost is too large.');
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

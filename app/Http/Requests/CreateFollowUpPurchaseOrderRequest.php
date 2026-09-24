<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateFollowUpPurchaseOrderRequest extends FormRequest
{
    private const COST_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'submission_token' => ['bail', 'required', 'string', 'uuid'],
            'supplier_name' => ['bail', 'required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['bail', 'required', 'array', 'min:1', 'max:100'],
            'items.*' => ['bail', 'required', 'array:selected,source_purchase_order_item_id,expected_unit_cost'],
            'items.*.selected' => ['nullable', 'string', 'in:1'],
            'items.*.source_purchase_order_item_id' => ['bail', 'required', 'integer', 'min:1', 'distinct:strict'],
            'items.*.expected_unit_cost' => ['bail', 'required', 'string', 'regex:'.self::COST_PATTERN],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['submission_token', 'supplier_name', 'notes'] as $field) {
            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }
            $value = $field === 'submission_token'
                ? strtolower(trim($value))
                : preg_replace('/[\p{Z}\s]+/u', ' ', $value);
            if (is_string($value)) {
                $value = trim($value);
                $normalized[$field] = $field === 'notes' && $value === '' ? null : $value;
            }
        }

        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (is_array($item) && is_string($item['expected_unit_cost'] ?? null)) {
                    $items[$index]['expected_unit_cost'] = trim($item['expected_unit_cost']);
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
            if (array_diff($topLevel, ['_token', 'items', 'notes', 'submission_token', 'supplier_name']) !== []) {
                $validator->errors()->add('request', 'The follow-up submission contains unexpected fields.');
            }

            $items = $this->input('items');
            if (! is_array($items)) {
                return;
            }
            $selected = 0;
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (($item['selected'] ?? null) === '1') {
                    $selected++;
                }
                $cost = $item['expected_unit_cost'] ?? null;
                if (is_string($cost) && preg_match(self::COST_PATTERN, $cost) === 1) {
                    $canonical = $this->canonicalUnsigned($cost, 2);
                    if (strlen(strtok($canonical, '.')) > 10 || bccomp($canonical, '9999999999.99', 2) === 1) {
                        $validator->errors()->add("items.{$index}.expected_unit_cost", 'The expected unit cost is too large.');
                    }
                }
            }
            if ($selected === 0) {
                $validator->errors()->add('items', 'Select at least one outstanding source line.');
            }
        }];
    }

    /** @return list<array{source_purchase_order_item_id: int, expected_unit_cost: string}> */
    public function selectedItems(): array
    {
        return collect($this->validated('items'))
            ->filter(fn (array $item): bool => ($item['selected'] ?? null) === '1')
            ->map(fn (array $item): array => [
                'source_purchase_order_item_id' => $item['source_purchase_order_item_id'],
                'expected_unit_cost' => $item['expected_unit_cost'],
            ])
            ->values()
            ->all();
    }

    private function canonicalUnsigned(string $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, $scale, '0');
    }
}

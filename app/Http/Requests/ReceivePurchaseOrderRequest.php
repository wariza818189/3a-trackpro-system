<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && in_array($this->user()->role, [User::ROLE_ADMIN, 'staff'], true);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'string', 'uuid'],
            'reference_text' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:purchase_order_item_id,accepted_quantity,actual_unit_cost'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'min:1'],
            'items.*.accepted_quantity' => ['nullable', 'string', 'regex:/\A\d+(?:\.\d{1,3})?\z/D'],
            'items.*.actual_unit_cost' => ['nullable', 'string', 'regex:/\A\d+(?:\.\d{1,2})?\z/D'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['submission_token', 'reference_text', 'notes'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }
        if (isset($normalized['submission_token'])) {
            $normalized['submission_token'] = strtolower($normalized['submission_token']);
        }
        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['accepted_quantity', 'actual_unit_cost'] as $field) {
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
                $quantity = $item['accepted_quantity'] ?? null;
                $cost = $item['actual_unit_cost'] ?? null;
                if ($quantity !== null && $quantity !== '' && ($cost === null || $cost === '')) {
                    $validator->errors()->add("items.{$index}.actual_unit_cost", 'Enter the actual unit cost for each accepted line.');
                }
                if (($quantity === null || $quantity === '') && $cost !== null && $cost !== '') {
                    $validator->errors()->add("items.{$index}.accepted_quantity", 'Enter an accepted quantity with the actual unit cost.');
                }
            }
        }];
    }

    /** @return list<array<string, mixed>> */
    public function acceptedItems(): array
    {
        $accepted = [];
        foreach ($this->validated('items') as $item) {
            if (($item['accepted_quantity'] ?? null) === null || $item['accepted_quantity'] === '') {
                continue;
            }
            $accepted[] = [
                'purchase_order_item_id' => $item['purchase_order_item_id'],
                'accepted_quantity' => $item['accepted_quantity'],
                'actual_unit_cost' => $item['actual_unit_cost'],
            ];
        }

        return $accepted;
    }
}

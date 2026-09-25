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
            'items.*' => ['required', 'array:purchase_order_item_id,accepted_quantity,actual_unit_cost,damaged_quantity,damage_note'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'items.*.accepted_quantity' => ['nullable', 'string', 'regex:/\A\d+(?:\.\d{1,3})?\z/D'],
            'items.*.actual_unit_cost' => ['nullable', 'string', 'regex:/\A\d+(?:\.\d{1,2})?\z/D'],
            'items.*.damaged_quantity' => ['nullable', 'string', 'regex:/\A\d+(?:\.\d{1,3})?\z/D'],
            'items.*.damage_note' => ['nullable', 'string', 'max:1000'],
            'recorded_by' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'purchase_order_id' => ['prohibited'],
            'total_cost' => ['prohibited'],
            'restock_id' => ['prohibited'],
            'items.*.product_variant_id' => ['prohibited'],
            'items.*.product_name_snapshot' => ['prohibited'],
            'items.*.size_snapshot' => ['prohibited'],
            'items.*.type_series_snapshot' => ['prohibited'],
            'items.*.thickness_snapshot' => ['prohibited'],
            'items.*.unit_snapshot' => ['prohibited'],
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
                foreach (['accepted_quantity', 'actual_unit_cost', 'damaged_quantity'] as $field) {
                    if (isset($item[$field]) && is_string($item[$field])) {
                        $items[$index][$field] = trim($item[$field]);
                    }
                }
                if (isset($item['damage_note']) && is_string($item['damage_note'])) {
                    $note = preg_replace('/\s+/u', ' ', trim($item['damage_note']));
                    if (is_string($note)) {
                        $items[$index]['damage_note'] = $note;
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
            $hasEvidence = false;
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
                $damage = $item['damaged_quantity'] ?? null;
                $damageNote = $item['damage_note'] ?? null;
                if ($damage !== null && $damage !== '') {
                    $hasEvidence = true;
                    if ($damageNote === null || $damageNote === '') {
                        $validator->errors()->add("items.{$index}.damage_note", 'Enter a note for each damaged quantity.');
                    }
                } elseif ($damageNote !== null && $damageNote !== '') {
                    $validator->errors()->add("items.{$index}.damaged_quantity", 'Enter a damaged quantity with the damage note.');
                }
                if ($quantity !== null && $quantity !== '') {
                    $hasEvidence = true;
                }
            }
            if (! $hasEvidence) {
                $validator->errors()->add('items', 'Enter at least one accepted or damaged quantity.');
            }
        }];
    }

    /** @return list<array<string, mixed>> */
    public function receiptItems(): array
    {
        $received = [];
        foreach ($this->validated('items') as $item) {
            $hasAccepted = ($item['accepted_quantity'] ?? null) !== null && $item['accepted_quantity'] !== '';
            $hasDamage = ($item['damaged_quantity'] ?? null) !== null && $item['damaged_quantity'] !== '';
            if (! $hasAccepted && ! $hasDamage) {
                continue;
            }
            $line = ['purchase_order_item_id' => $item['purchase_order_item_id']];
            if ($hasAccepted) {
                $line['accepted_quantity'] = $item['accepted_quantity'];
                $line['actual_unit_cost'] = $item['actual_unit_cost'];
            }
            if ($hasDamage) {
                $line['damaged_quantity'] = $item['damaged_quantity'];
                $line['damage_note'] = $item['damage_note'];
            }
            $received[] = $line;
        }

        return $received;
    }
}

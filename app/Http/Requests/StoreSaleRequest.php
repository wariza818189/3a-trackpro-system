<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    private const QUANTITY_PATTERN = '/\A\d+(?:\.\d{1,3})?\z/D';

    private const PRICE_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->isActive()
            && in_array($user->role, [User::ROLE_ADMIN, 'staff'], true);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'submission_token' => ['bail', 'required', 'string', 'uuid', 'max:36'],
            'amount_tendered' => ['bail', 'required', 'string', 'regex:'.self::PRICE_PATTERN],
            'items' => ['bail', 'required', 'array', 'min:1', 'max:100'],
            'items.*' => ['bail', 'required', 'array:product_variant_id,quantity,expected_unit_price'],
            'items.*.product_variant_id' => ['bail', 'required', 'integer', 'min:1'],
            'items.*.quantity' => ['bail', 'required', 'string', 'regex:'.self::QUANTITY_PATTERN],
            'items.*.expected_unit_price' => ['bail', 'required', 'string', 'regex:'.self::PRICE_PATTERN],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        $token = $this->input('submission_token');
        if (is_string($token)) {
            $normalized['submission_token'] = strtolower(trim($token));
        }
        $tender = $this->input('amount_tendered');
        if (is_string($tender)) {
            $normalized['amount_tendered'] = trim($tender);
        }
        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['quantity', 'expected_unit_price'] as $field) {
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
            $allowed = ['_token', 'amount_tendered', 'items', 'submission_token'];
            if (array_diff($topLevel, $allowed) !== []) {
                $validator->errors()->add('request', 'The checkout contains unexpected fields.');
            }

            $this->validateMoney($validator, 'amount_tendered', $this->input('amount_tendered'), false, 14, 'The tendered amount');

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
                        $validator->errors()->add("items.{$index}.quantity", 'The quantity is too large.');
                    } elseif (bccomp($canonical, '0.000', 3) !== 1) {
                        $validator->errors()->add("items.{$index}.quantity", 'The quantity must be greater than zero.');
                    }
                }
                $this->validateMoney($validator, "items.{$index}.expected_unit_price", $item['expected_unit_price'] ?? null, true, 10, 'The expected price');
            }
        }];
    }

    private function validateMoney(Validator $validator, string $field, mixed $value, bool $positive, int $integerDigits, string $label): void
    {
        if (! is_string($value) || preg_match(self::PRICE_PATTERN, $value) !== 1) {
            return;
        }
        $canonical = $this->canonicalUnsigned($value, 2);
        if (strlen(strtok($canonical, '.')) > $integerDigits) {
            $validator->errors()->add($field, "{$label} is too large.");
        } elseif ($positive && bccomp($canonical, '0.00', 2) !== 1) {
            $validator->errors()->add($field, "{$label} must be greater than zero.");
        }
    }

    private function canonicalUnsigned(string $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, $scale, '0');
    }
}

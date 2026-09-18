<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OpenCashRegisterRequest extends FormRequest
{
    private const MONEY_PATTERN = '/\A\d+(?:\.\d{1,2})?\z/D';

    private const MAX_OPENING_CASH = '99999999999999.99';

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
            'opening_cash' => ['bail', 'required', 'string', 'regex:'.self::MONEY_PATTERN],
            'id' => ['prohibited'],
            'cash_register_session_id' => ['prohibited'],
            'register_id' => ['prohibited'],
            'opened_by' => ['prohibited'],
            'opened_at' => ['prohibited'],
            'active_slot' => ['prohibited'],
            'closed_by' => ['prohibited'],
            'closed_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $openingCash = $this->input('opening_cash');
        if (is_string($openingCash)) {
            $this->merge(['opening_cash' => trim($openingCash)]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $topLevel = array_keys($this->all());
            sort($topLevel);
            if (array_diff($topLevel, ['_token', 'opening_cash']) !== []) {
                $validator->errors()->add('request', 'The register request contains unexpected fields.');
            }

            if ($validator->errors()->has('opening_cash')) {
                return;
            }

            $openingCash = $this->input('opening_cash');
            if (! is_string($openingCash) || preg_match(self::MONEY_PATTERN, $openingCash) !== 1) {
                return;
            }

            $canonical = $this->canonicalUnsignedMoney($openingCash);
            if (strlen(strtok($canonical, '.')) > 14
                || bccomp($canonical, self::MAX_OPENING_CASH, 2) === 1) {
                $validator->errors()->add('opening_cash', 'The opening cash amount is too large.');
            }
        }];
    }

    private function canonicalUnsignedMoney(string $value): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, 2, '0');
    }
}

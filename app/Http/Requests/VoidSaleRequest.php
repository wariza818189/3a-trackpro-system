<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['bail', 'required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');
        if (! is_string($reason)) {
            return;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($reason));
        if (is_string($normalized)) {
            $this->merge(['reason' => $normalized]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['_token', '_method', 'reason']) !== []) {
                $validator->errors()->add('request', 'The Sale Void request contains unexpected fields.');
            }
        }];
    }
}

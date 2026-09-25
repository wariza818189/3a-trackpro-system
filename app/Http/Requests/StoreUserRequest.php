<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'role' => ['sometimes', Rule::in(['admin', 'staff'])],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'status' => ['prohibited'],
            'remember_token' => ['prohibited'],
            'id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => User::normalizeUsername($this->input('username'))]);
        }
    }
}

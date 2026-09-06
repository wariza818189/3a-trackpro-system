<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Category::normalizeCatalogField('name', $this->input('name'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('name')) {
                return;
            }

            $category = $this->route('category');
            if (Category::query()->whereKeyNot($category?->getKey())
                ->whereRaw('LOWER(name) = LOWER(?)', [$this->input('name')])->exists()) {
                $validator->errors()->add('name', 'A category with this name already exists.');
            }
        }];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150'],
            'category_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Product::normalizeCatalogField('name', $this->input('name'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = $this->route('category');
            if ($validator->errors()->has('name') || ! $category) {
                return;
            }

            if (Product::query()->where('category_id', $category->getKey())
                ->whereRaw('LOWER(name) = LOWER(?)', [$this->input('name')])->exists()) {
                $validator->errors()->add('name', 'This category already contains a product with this name.');
            }
        }];
    }
}

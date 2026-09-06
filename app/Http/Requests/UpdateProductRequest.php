<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150'],
            'category_id' => [
                'bail', 'required', 'integer',
                Rule::exists('categories', 'id')->where('status', Category::STATUS_ACTIVE),
            ],
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
            if ($validator->errors()->hasAny(['name', 'category_id'])) {
                return;
            }

            $product = $this->route('product');
            if (Product::query()->whereKeyNot($product?->getKey())
                ->where('category_id', $this->integer('category_id'))
                ->whereRaw('LOWER(name) = LOWER(?)', [$this->input('name')])->exists()) {
                $validator->errors()->add('name', 'This category already contains a product with this name.');
            }
        }];
    }
}

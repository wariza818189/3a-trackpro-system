<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use Illuminate\Validation\Validator;

class UpdateProductVariantRequest extends StoreProductVariantRequest
{
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateWholeThreshold($validator),
            function (Validator $validator): void {
                $identity = ['size', 'type_series', 'thickness', 'unit'];
                if ($validator->errors()->hasAny($identity)) {
                    return;
                }

                $variant = $this->route('productVariant');
                if (! $variant instanceof ProductVariant) {
                    return;
                }

                $query = ProductVariant::query()
                    ->whereKeyNot($variant->getKey())
                    ->where('product_id', $variant->product_id);

                foreach ($identity as $field) {
                    $query->whereRaw("LOWER({$field}) = LOWER(?)", [$this->input($field)]);
                }

                if ($query->exists()) {
                    $validator->errors()->add('size', 'This product already contains a variant with the same identity and unit.');
                }
            },
        ];
    }
}

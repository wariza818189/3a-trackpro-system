<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductVariantRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    private const IDENTITY_FIELDS = ['size', 'type_series', 'thickness', 'unit', 'quantity_mode'];

    public function index(Request $request): View
    {
        $admin = $request->user()->can('access-admin');
        $status = $admin && in_array($request->query('status'), ['active', 'archived', 'all'], true)
            ? $request->query('status')
            : 'active';
        $unit = in_array($request->query('unit'), ProductVariant::SUPPORTED_UNITS, true)
            ? $request->query('unit') : null;
        $categoryId = filter_var($request->query('category'), FILTER_VALIDATE_INT) ?: null;
        $productId = filter_var($request->query('product'), FILTER_VALIDATE_INT) ?: null;
        $lowStock = $request->boolean('low_stock');
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        $variants = ProductVariant::query()
            ->with('product.category')
            ->when(! $admin, fn (Builder $query) => $query->inActiveHierarchy())
            ->when($admin && $status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($unit, fn (Builder $query) => $query->where('unit', $unit))
            ->when($productId, fn (Builder $query) => $query->where('product_id', $productId))
            ->when($categoryId, fn (Builder $query) => $query->whereHas(
                'product', fn (Builder $product) => $product->where('category_id', $categoryId),
            ))
            ->when($lowStock, fn (Builder $query) => $query->whereColumn('current_stock', '<=', 'low_stock_threshold'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $pattern = "%{$escaped}%";
                $query->where(function (Builder $identity) use ($pattern): void {
                    $identity->whereHas('product', fn (Builder $product) => $product->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
                    foreach (['size', 'type_series', 'thickness', 'unit'] as $field) {
                        $identity->orWhereRaw("{$field} LIKE ? ESCAPE '!'", [$pattern]);
                    }
                });
            })
            ->orderBy('product_id')
            ->orderBy('size')
            ->orderBy('type_series')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()->when(! $admin, fn (Builder $query) => $query->active())->orderBy('name')->get();
        $products = Product::query()->when(! $admin, fn (Builder $query) => $query->inActiveHierarchy())->orderBy('name')->get();

        return view('product-variants.index', compact(
            'variants', 'categories', 'products', 'status', 'unit', 'categoryId', 'productId', 'lowStock', 'search', 'admin',
        ));
    }

    public function create(Product $product): View
    {
        $product->load('category');
        abort_unless(
            $product->status === Product::STATUS_ACTIVE && $product->category->status === Category::STATUS_ACTIVE,
            409,
            'Variants can only be created under an active product and category.',
        );

        return view('product-variants.create', compact('product'));
    }

    public function store(StoreProductVariantRequest $request, Product $product): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $product): void {
                $category = Category::query()->whereKey($product->category_id)->lockForUpdate()->firstOrFail();
                $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());

                if ((int) $lockedProduct->category_id !== (int) $category->getKey()
                    || $category->status !== Category::STATUS_ACTIVE
                    || $lockedProduct->status !== Product::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['product_id' => 'Variants require an active product and category.']);
                }

                $variant = new ProductVariant($request->safe()->only([
                    'size', 'type_series', 'thickness', 'unit', 'quantity_mode',
                    'cost_price', 'selling_price', 'low_stock_threshold',
                ]));
                $variant->product_id = $lockedProduct->getKey();
                $variant->status = ProductVariant::STATUS_ACTIVE;
                $variant->save();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('product-variants.index')->with('success', 'Product variant created.');
    }

    public function edit(ProductVariant $productVariant): View
    {
        abort_unless($productVariant->status === ProductVariant::STATUS_ACTIVE, 409, 'Archived variants must be reactivated before editing.');
        $productVariant->load('product.category');
        abort_unless(
            $productVariant->product->status === Product::STATUS_ACTIVE
                && $productVariant->product->category->status === Category::STATUS_ACTIVE,
            409,
            'Variants can only be edited under an active product and category.',
        );

        $hasRestock = $productVariant->restockItems()->exists();
        $identityLocked = $hasRestock
            || $productVariant->saleItems()->exists()
            || $productVariant->stockMovements()->exists()
            || (float) $productVariant->current_stock !== 0.0;
        $costLocked = $hasRestock;

        return view('product-variants.edit', compact('productVariant', 'identityLocked', 'costLocked'));
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $productVariant): RedirectResponse
    {
        $data = $request->safe()->only([
            'size', 'type_series', 'thickness', 'unit', 'quantity_mode',
            'cost_price', 'selling_price', 'low_stock_threshold',
        ]);

        try {
            DB::transaction(function () use ($data, $productVariant): void {
                $productSnapshot = Product::query()->findOrFail($productVariant->product_id);
                $category = Category::query()->whereKey($productSnapshot->category_id)->lockForUpdate()->firstOrFail();
                $product = Product::query()->lockForUpdate()->findOrFail($productVariant->product_id);
                $variant = ProductVariant::query()->lockForUpdate()->findOrFail($productVariant->getKey());

                if ((int) $product->category_id !== (int) $category->getKey()
                    || (int) $variant->product_id !== (int) $product->getKey()) {
                    throw ValidationException::withMessages(['product_id' => 'The catalog hierarchy changed. Please retry.']);
                }
                if ($category->status !== Category::STATUS_ACTIVE
                    || $product->status !== Product::STATUS_ACTIVE
                    || $variant->status !== ProductVariant::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['status' => 'Only variants in an active hierarchy can be edited.']);
                }

                $variant->fill($data);
                $identityChanged = $variant->isDirty(self::IDENTITY_FIELDS);
                $costChanged = $variant->isDirty('cost_price');
                $hasRestock = $costChanged || $identityChanged ? $variant->restockItems()->exists() : false;

                if ($identityChanged && ($this->hasActivity($variant, $hasRestock) || (float) $variant->getRawOriginal('current_stock') !== 0.0)) {
                    throw ValidationException::withMessages([
                        'size' => 'Variant identity, unit, and quantity mode are locked after inventory or transaction activity.',
                    ]);
                }
                if ($costChanged && $hasRestock) {
                    throw ValidationException::withMessages(['cost_price' => 'Cost price is managed by restocking after the first restock.']);
                }

                $variant->save();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('product-variants.index')->with('success', 'Product variant updated.');
    }

    public function archive(ProductVariant $productVariant): RedirectResponse
    {
        DB::transaction(function () use ($productVariant): void {
            $productSnapshot = Product::query()->findOrFail($productVariant->product_id);
            $category = Category::query()->whereKey($productSnapshot->category_id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($productVariant->product_id)->lockForUpdate()->firstOrFail();
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($productVariant->getKey());

            if ((int) $product->category_id !== (int) $category->getKey()
                || (int) $variant->product_id !== (int) $product->getKey()) {
                throw ValidationException::withMessages(['status' => 'The catalog hierarchy changed. Please retry.']);
            }
            if ($variant->status !== ProductVariant::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Only an active variant can be archived.']);
            }
            if ((float) $variant->current_stock > 0.0) {
                throw ValidationException::withMessages(['status' => 'A variant with stock on hand cannot be archived.']);
            }

            $variant->status = ProductVariant::STATUS_ARCHIVED;
            $variant->save();
        });

        return back()->with('success', 'Product variant archived.');
    }

    public function reactivate(ProductVariant $productVariant): RedirectResponse
    {
        try {
            DB::transaction(function () use ($productVariant): void {
                $productSnapshot = Product::query()->findOrFail($productVariant->product_id);
                $category = Category::query()->whereKey($productSnapshot->category_id)->lockForUpdate()->firstOrFail();
                $product = Product::query()->whereKey($productVariant->product_id)->lockForUpdate()->firstOrFail();
                $variant = ProductVariant::query()->lockForUpdate()->findOrFail($productVariant->getKey());

                if ((int) $product->category_id !== (int) $category->getKey()
                    || (int) $variant->product_id !== (int) $product->getKey()) {
                    throw ValidationException::withMessages(['status' => 'The catalog hierarchy changed. Please retry.']);
                }
                if ($variant->status !== ProductVariant::STATUS_ARCHIVED) {
                    throw ValidationException::withMessages(['status' => 'Only an archived variant can be reactivated.']);
                }
                if ($category->status !== Category::STATUS_ACTIVE || $product->status !== Product::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['status' => 'Reactivate the variant product and category first.']);
                }

                $variant->status = ProductVariant::STATUS_ACTIVE;
                $variant->save();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return back()->with('success', 'Product variant reactivated.');
    }

    private function hasActivity(ProductVariant $variant, bool $hasRestock): bool
    {
        return $hasRestock || $variant->saleItems()->exists() || $variant->stockMovements()->exists();
    }

    private function throwIfDuplicate(QueryException $exception): void
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $sqliteUnique = $driverCode === 19
            && str_contains(strtolower($exception->getMessage()), 'unique constraint failed');

        if (($sqlState === '23000' && $driverCode === 1062) || $sqliteUnique) {
            throw ValidationException::withMessages([
                'size' => 'This product already contains a variant with the same identity and unit.',
            ]);
        }
    }
}

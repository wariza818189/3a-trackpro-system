<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
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

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->user()->can('access-admin');
        $status = $admin && in_array($request->query('status'), ['active', 'archived', 'all'], true)
            ? $request->query('status')
            : 'active';
        $categoryId = filter_var($request->query('category'), FILTER_VALIDATE_INT) ?: null;
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        $products = Product::query()
            ->with('category')
            ->withCount([
                'variants' => fn ($query) => $admin ? $query : $query->where('status', ProductVariant::STATUS_ACTIVE),
            ])
            ->when(! $admin, fn (Builder $query) => $query->inActiveHierarchy())
            ->when($admin && $status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $query->whereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()
            ->when(! $admin, fn (Builder $query) => $query->active())
            ->orderBy('name')->get();

        return view('products.index', compact('products', 'categories', 'status', 'categoryId', 'search', 'admin'));
    }

    public function create(Category $category): View
    {
        abort_unless($category->status === Category::STATUS_ACTIVE, 409, 'Products can only be created in active categories.');

        return view('products.create', compact('category'));
    }

    public function store(StoreProductRequest $request, Category $category): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $category): void {
                $lockedCategory = Category::query()->lockForUpdate()->findOrFail($category->getKey());
                if ($lockedCategory->status !== Category::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['category_id' => 'Products can only be created in active categories.']);
                }

                $product = new Product($request->safe()->only('name'));
                $product->category_id = $lockedCategory->getKey();
                $product->status = Product::STATUS_ACTIVE;
                $product->save();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        abort_unless($product->status === Product::STATUS_ACTIVE, 409, 'Archived products must be reactivated before editing.');
        $categories = Category::query()->active()->orderBy('name')->get();

        return view('products.edit', compact('product', 'categories'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'category_id']);

        try {
            DB::transaction(function () use ($data, $product): void {
                $categoryIds = array_values(array_unique([(int) $product->category_id, (int) $data['category_id']]));
                $categories = Category::query()->whereKey($categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());

                if (! in_array((int) $locked->category_id, $categoryIds, true)) {
                    throw ValidationException::withMessages(['category_id' => 'The product changed while this form was open. Please retry.']);
                }
                if ($locked->status !== Product::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['name' => 'Archived products must be reactivated before editing.']);
                }

                $destination = $categories->get((int) $data['category_id']);
                if (! $destination || $destination->status !== Category::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['category_id' => 'The selected category must be active.']);
                }

                if ((int) $locked->category_id !== (int) $data['category_id'] && $this->hasInventoryOrHistory($locked)) {
                    throw ValidationException::withMessages(['category_id' => 'A product with inventory or transaction history cannot change category.']);
                }

                $locked->fill($data)->save();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    public function archive(Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product): void {
            Category::query()->whereKey($product->category_id)->lockForUpdate()->firstOrFail();
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            if ((int) $locked->category_id !== (int) $product->category_id) {
                throw ValidationException::withMessages(['status' => 'The product hierarchy changed. Please retry.']);
            }
            if ($locked->status !== Product::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Only an active product can be archived.']);
            }
            if ($locked->variants()->where('status', ProductVariant::STATUS_ACTIVE)->exists()) {
                throw ValidationException::withMessages(['status' => 'Archive all active variants for this product first.']);
            }

            $locked->status = Product::STATUS_ARCHIVED;
            $locked->save();
        });

        return back()->with('success', 'Product archived.');
    }

    public function reactivate(Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product): void {
            $category = Category::query()->whereKey($product->category_id)->lockForUpdate()->firstOrFail();
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            if ((int) $locked->category_id !== (int) $category->getKey()) {
                throw ValidationException::withMessages(['status' => 'The product hierarchy changed. Please retry.']);
            }
            if ($locked->status !== Product::STATUS_ARCHIVED) {
                throw ValidationException::withMessages(['status' => 'Only an archived product can be reactivated.']);
            }
            if ($category->status !== Category::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Reactivate the product category first.']);
            }

            $locked->status = Product::STATUS_ACTIVE;
            $locked->save();
        });

        return back()->with('success', 'Product reactivated.');
    }

    private function hasInventoryOrHistory(Product $product): bool
    {
        return $product->variants()->where(function (Builder $query): void {
            $query->where('current_stock', '<>', 0)
                ->orWhereHas('saleItems')
                ->orWhereHas('restockItems')
                ->orWhereHas('stockMovements');
        })->exists();
    }

    private function throwIfDuplicate(QueryException $exception): void
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $sqliteUnique = $driverCode === 19
            && str_contains(strtolower($exception->getMessage()), 'unique constraint failed');

        if (($sqlState === '23000' && $driverCode === 1062) || $sqliteUnique) {
            throw ValidationException::withMessages(['name' => 'This category already contains a product with this name.']);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningInventoryRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\RecordOpeningInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpeningInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $categoryId = filter_var($request->query('category'), FILTER_VALIDATE_INT) ?: null;
        $productId = filter_var($request->query('product'), FILTER_VALIDATE_INT) ?: null;
        $initialization = in_array($request->query('initialization'), ['all', 'initialized', 'not_initialized'], true)
            ? $request->query('initialization')
            : 'all';

        $variants = ProductVariant::query()
            ->with('product.category')
            ->withExists([
                'openingInventoryMovements as opening_inventory_recorded',
                'stockMovements as has_stock_movement',
                'saleItems as has_sale_history',
                'restockItems as has_restock_history',
            ])
            ->when($categoryId, fn (Builder $query) => $query->whereHas(
                'product', fn (Builder $product) => $product->where('category_id', $categoryId),
            ))
            ->when($productId, fn (Builder $query) => $query->where('product_id', $productId))
            ->when($initialization === 'initialized', fn (Builder $query) => $query->whereHas('openingInventoryMovements'))
            ->when($initialization === 'not_initialized', fn (Builder $query) => $query->whereDoesntHave('openingInventoryMovements'))
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

        $categories = Category::query()->orderBy('name')->get();
        $products = Product::query()->orderBy('name')->get();

        return view('opening-inventory.index', compact(
            'variants', 'categories', 'products', 'search', 'categoryId', 'productId', 'initialization',
        ));
    }

    public function create(ProductVariant $productVariant): View
    {
        $productVariant->load('product.category');

        abort_unless($this->isEligibleForOpening($productVariant), 409, 'Opening inventory is unavailable for this variant.');

        return view('opening-inventory.create', compact('productVariant'));
    }

    public function store(
        StoreOpeningInventoryRequest $request,
        ProductVariant $productVariant,
        RecordOpeningInventory $recordOpeningInventory,
    ): RedirectResponse {
        $productVariant->loadMissing('product:id,category_id');

        $recordOpeningInventory->execute(
            $productVariant,
            $request->user(),
            $request->validated('opening_quantity'),
            $request->validated('reason'),
        );

        return redirect()->route('opening-inventory.index')->with('success', 'Opening inventory recorded.');
    }

    private function isEligibleForOpening(ProductVariant $variant): bool
    {
        return $variant->status === ProductVariant::STATUS_ACTIVE
            && $variant->product->status === Product::STATUS_ACTIVE
            && $variant->product->category->status === Category::STATUS_ACTIVE
            && (string) $variant->current_stock === '0.000'
            && ! $variant->stockMovements()->exists()
            && ! $variant->saleItems()->exists()
            && ! $variant->restockItems()->exists();
    }
}

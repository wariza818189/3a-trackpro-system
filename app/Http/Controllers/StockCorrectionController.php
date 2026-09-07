<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockCorrectionRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\Inventory\RecordStockCorrection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCorrectionController extends Controller
{
    public function index(Request $request): View
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $categoryId = filter_var($request->query('category'), FILTER_VALIDATE_INT) ?: null;
        $productId = filter_var($request->query('product'), FILTER_VALIDATE_INT) ?: null;

        $variants = ProductVariant::query()
            ->with('product.category')
            ->inActiveHierarchy()
            ->whereHas('openingInventoryMovements')
            ->when($categoryId, fn (Builder $query) => $query->whereHas(
                'product', fn (Builder $product) => $product->where('category_id', $categoryId),
            ))
            ->when($productId, fn (Builder $query) => $query->where('product_id', $productId))
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
            ->paginate(15, ['*'], 'variants_page')
            ->withQueryString();

        $history = StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_CORRECTION)
            ->with(['variant.product.category', 'performedBy:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(15, ['*'], 'history_page')
            ->withQueryString();

        $categories = Category::query()->active()->orderBy('name')->get();
        $products = Product::query()->inActiveHierarchy()->orderBy('name')->get();

        return view('stock-corrections.index', compact(
            'variants', 'history', 'categories', 'products', 'search', 'categoryId', 'productId',
        ));
    }

    public function create(ProductVariant $productVariant): View
    {
        $latestMovementId = filter_var(
            StockMovement::query()
                ->where('product_variant_id', $productVariant->getKey())
                ->latest('id')
                ->value('id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        abort_if($latestMovementId === false, 409, 'Stock Correction is unavailable for this variant.');

        $variant = ProductVariant::query()
            ->with('product.category')
            ->findOrFail($productVariant->getKey());

        abort_unless($this->isEligible($variant), 409, 'Stock Correction is unavailable for this variant.');

        return view('stock-corrections.create', [
            'productVariant' => $variant,
            'latestMovementId' => $latestMovementId,
        ]);
    }

    public function store(
        StoreStockCorrectionRequest $request,
        ProductVariant $productVariant,
        RecordStockCorrection $recordStockCorrection,
    ): RedirectResponse {
        $recordStockCorrection->execute(
            $productVariant,
            $request->user(),
            $request->validated('corrected_stock'),
            $request->validated('expected_movement_id'),
            $request->validated('reason'),
        );

        return redirect()->route('stock-corrections.index')->with('success', 'Stock Correction recorded.');
    }

    private function isEligible(ProductVariant $variant): bool
    {
        return $variant->status === ProductVariant::STATUS_ACTIVE
            && $variant->product->status === Product::STATUS_ACTIVE
            && $variant->product->category->status === Category::STATUS_ACTIVE
            && $variant->openingInventoryMovements()->exists();
    }
}

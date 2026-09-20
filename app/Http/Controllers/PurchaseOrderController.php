<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\ProductVariant;
use App\Queries\Procurement\LowStockPurchaseOrderRecommendations;
use App\Queries\Procurement\ProcurementVariantCatalogQuery;
use App\Services\Procurement\CreatePurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function create(
        Request $request,
        LowStockPurchaseOrderRecommendations $recommendations,
        ProcurementVariantCatalogQuery $catalog,
    ): View {
        $oldToken = $request->session()->getOldInput('submission_token');
        $submissionToken = is_string($oldToken)
            && preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D', $oldToken) === 1
            ? strtolower($oldToken)
            : Str::uuid()->toString();

        $priorities = $recommendations->all()->get();
        $uncovered = $priorities->where('coverage_state', 'uncovered')->values();
        $covered = $priorities->where('coverage_state', 'covered')->values();
        $priorityIds = $priorities->modelKeys();

        $otherVariants = $catalog->query()
            ->select([
                'product_variants.id', 'product_variants.product_id', 'product_variants.size',
                'product_variants.type_series', 'product_variants.thickness', 'product_variants.unit',
                'product_variants.quantity_mode', 'product_variants.current_stock',
                'product_variants.low_stock_threshold',
            ])
            ->with(['product:id,category_id,name', 'product.category:id,name'])
            ->when($priorityIds !== [], fn ($query) => $query->whereNotIn('product_variants.id', $priorityIds))
            ->orderBy('product_variants.product_id')
            ->orderBy('product_variants.size')
            ->orderBy('product_variants.type_series')
            ->orderBy('product_variants.thickness')
            ->orderBy('product_variants.unit')
            ->orderBy('product_variants.id')
            ->get();

        $eligible = $priorities->concat($otherVariants)->keyBy('id');
        [$oldDraftRows, $removedOldSelectionCount] = $this->oldDraftRows(
            $request->session()->getOldInput('items'),
            $eligible,
        );

        return view('purchase-orders.create', compact(
            'submissionToken',
            'uncovered',
            'covered',
            'otherVariants',
            'oldDraftRows',
            'removedOldSelectionCount',
        ));
    }

    public function store(
        StorePurchaseOrderRequest $request,
        CreatePurchaseOrder $createPurchaseOrder,
    ): RedirectResponse {
        try {
            $purchaseOrder = $createPurchaseOrder->execute(
                $request->user(),
                $request->validated('submission_token'),
                $request->validated('supplier_name'),
                $request->validated('notes'),
                $request->validated('items'),
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (array_keys($errors) !== ['submission_token']) {
                throw $exception;
            }

            $oldInput = $request->only(['supplier_name', 'notes', 'items']);
            $oldInput['submission_token'] = Str::uuid()->toString();

            return redirect()->route('purchase-orders.create')
                ->withErrors($errors)
                ->withInput($oldInput);
        }

        return redirect()->route('purchase-orders.create')->with('purchase_order_confirmation', [
            'id' => $purchaseOrder->getKey(),
            'supplier_name' => $purchaseOrder->supplier_name,
            'status' => $purchaseOrder->status,
            'line_count' => $purchaseOrder->items->count(),
            'replayed' => ! $purchaseOrder->wasRecentlyCreated,
        ]);
    }

    /**
     * @param  Collection<int, ProductVariant>  $eligible
     * @return array{Collection<int, array{variant: ProductVariant, ordered_quantity: string, expected_unit_cost: string}>, int}
     */
    private function oldDraftRows(mixed $oldItems, Collection $eligible): array
    {
        if (! is_array($oldItems)) {
            return [collect(), 0];
        }

        $rows = collect();
        $removed = 0;
        foreach (array_values($oldItems) as $item) {
            if (! is_array($item)) {
                $removed++;

                continue;
            }

            $id = filter_var($item['product_variant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $variant = $id === false ? null : $eligible->get((int) $id);
            if (! $variant instanceof ProductVariant) {
                $removed++;

                continue;
            }

            $rows->push([
                'variant' => $variant,
                'ordered_quantity' => is_string($item['ordered_quantity'] ?? null) ? $item['ordered_quantity'] : '',
                'expected_unit_cost' => is_string($item['expected_unit_cost'] ?? null) ? $item['expected_unit_cost'] : '',
            ]);
        }

        return [$rows, $removed];
    }
}

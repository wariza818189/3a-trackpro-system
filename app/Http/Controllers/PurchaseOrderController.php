<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Queries\Procurement\LowStockPurchaseOrderRecommendations;
use App\Queries\Procurement\ProcurementVariantCatalogQuery;
use App\Services\Procurement\CreatePurchaseOrder;
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->authorizeOperationalAccess($request);
        $validated = $request->validate([
            'supplier' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', Rule::in([
                PurchaseOrder::STATUS_PENDING,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                PurchaseOrder::STATUS_COMPLETED,
                PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER,
            ])],
        ]);

        $supplier = trim($validated['supplier'] ?? '');
        $status = $validated['status'] ?? null;
        $status = $status === '' ? null : $status;

        $purchaseOrders = PurchaseOrder::query()
            ->with('createdBy:id,name')
            ->withCount('items')
            ->withExists(['items as has_transfer_activity' => fn ($query) => $query
                ->where(fn ($activity) => $activity
                    ->whereHas('outgoingTransfer')
                    ->orWhereHas('incomingTransfer'))])
            ->when($supplier !== '', function ($query) use ($supplier): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $supplier);
                $query->whereRaw("supplier_name LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchase-orders.index', compact('purchaseOrders', 'supplier', 'status', 'admin'));
    }

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

        [$uncovered, $covered, $otherVariants, $eligible] = $this->selectionData($recommendations, $catalog);
        [$oldDraftRows, $removedOldSelectionCount] = $this->oldDraftRows(
            $request->session()->getOldInput('items'),
            $eligible,
        );
        $supplierName = $request->session()->getOldInput('supplier_name', '');
        $notesValue = $request->session()->getOldInput('notes', '');

        return view('purchase-orders.create', compact(
            'submissionToken',
            'supplierName',
            'notesValue',
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

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('purchase_order_confirmation', [
            'id' => $purchaseOrder->getKey(),
            'supplier_name' => $purchaseOrder->supplier_name,
            'status' => $purchaseOrder->status,
            'line_count' => $purchaseOrder->items->count(),
            'replayed' => ! $purchaseOrder->wasRecentlyCreated,
        ]);
    }

    public function edit(
        Request $request,
        PurchaseOrder $purchaseOrder,
        UpdatePurchaseOrder $updatePurchaseOrder,
        LowStockPurchaseOrderRecommendations $recommendations,
        ProcurementVariantCatalogQuery $catalog,
    ): View {
        $purchaseOrder->refresh();
        abort_unless($purchaseOrder->isEditable(), 409, 'This Purchase Order is read-only.');

        $hasOldInput = $request->session()->hasOldInput();
        $expectedRevision = $hasOldInput
            ? $request->session()->getOldInput('expected_revision')
            : $updatePurchaseOrder->revision($purchaseOrder);

        $purchaseOrder->refresh();
        $purchaseOrder->load([
            'items' => fn ($query) => $query
                ->orderBy('product_variant_id')
                ->orderBy('id'),
        ]);
        abort_unless($purchaseOrder->isEditable(), 409, 'This Purchase Order is read-only.');

        [$uncovered, $covered, $otherVariants, $eligible] = $this->selectionData($recommendations, $catalog);
        $persistedItems = $purchaseOrder->items->keyBy(
            fn (PurchaseOrderItem $item): int => (int) $item->product_variant_id,
        );
        $persistedVariants = ProductVariant::query()
            ->whereKey($persistedItems->keys()->all())
            ->get(['id', 'quantity_mode'])
            ->keyBy('id');
        $retainedPickerRows = $purchaseOrder->items->mapWithKeys(function (PurchaseOrderItem $item) use ($persistedVariants, $eligible): array {
            $variantId = (int) $item->product_variant_id;

            return [$variantId => $this->historicalDraftRow(
                $item,
                $persistedVariants->get($variantId),
                $eligible->has($variantId),
            )];
        });

        if ($hasOldInput) {
            [$draftRows, $removedOldSelectionCount] = $this->oldEditDraftRows(
                $request->session()->getOldInput('items'),
                $persistedItems,
                $persistedVariants,
                $eligible,
            );
            $supplierName = $request->session()->getOldInput('supplier_name', '');
            $notesValue = $request->session()->getOldInput('notes');
        } else {
            $draftRows = $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => $this->historicalDraftRow(
                $item,
                $persistedVariants->get((int) $item->product_variant_id),
                $eligible->has((int) $item->product_variant_id),
            ));
            $removedOldSelectionCount = 0;
            $supplierName = $purchaseOrder->supplier_name;
            $notesValue = $purchaseOrder->notes;
        }

        return view('purchase-orders.edit', compact(
            'purchaseOrder',
            'expectedRevision',
            'supplierName',
            'notesValue',
            'uncovered',
            'covered',
            'otherVariants',
            'retainedPickerRows',
            'draftRows',
            'removedOldSelectionCount',
        ));
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        UpdatePurchaseOrder $updatePurchaseOrder,
    ): RedirectResponse {
        try {
            $updated = $updatePurchaseOrder->execute(
                $request->user(),
                $purchaseOrder,
                $request->validated('expected_revision'),
                $request->validated('supplier_name'),
                $request->validated('notes'),
                $request->validated('items'),
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (array_key_exists('expected_revision', $errors)) {
                return redirect()->route('purchase-orders.edit', $purchaseOrder)
                    ->withErrors([
                        'expected_revision' => 'This Purchase Order changed while you were editing it. Review the latest values and try again.',
                    ]);
            }

            if (array_key_exists('purchase_order', $errors)) {
                $current = PurchaseOrder::query()->find($purchaseOrder->getKey());
                if ($current !== null && ! $current->isEditable()) {
                    return redirect()->route('purchase-orders.show', $current)
                        ->withErrors(['purchase_order' => 'This Purchase Order is now read-only and was not updated.']);
                }
            }

            throw $exception;
        }

        return redirect()->route('purchase-orders.show', $updated)
            ->with('success', 'Purchase Order updated successfully.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $admin = $this->authorizeOperationalAccess($request);
        $itemColumns = ['id', 'purchase_order_id', 'product_variant_id', 'product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot', 'ordered_quantity'];
        if ($admin) {
            $itemColumns[] = 'expected_unit_cost';
        }
        $purchaseOrder->load([
            'createdBy:id,name',
            'parent:id',
            'children' => fn ($query) => $query->select(['id', 'parent_purchase_order_id', 'status'])->orderBy('id'),
            'items' => fn ($query) => $query
                ->select($itemColumns)
                ->with([
                    'outgoingTransfer:id,source_purchase_order_item_id,target_purchase_order_item_id,quantity',
                    'outgoingTransfer.targetItem:id,purchase_order_id',
                    'outgoingTransfer.targetItem.purchaseOrder:id,status',
                    'incomingTransfer:id,source_purchase_order_item_id,target_purchase_order_item_id,quantity',
                    'incomingTransfer.sourceItem:id,purchase_order_id',
                    'incomingTransfer.sourceItem.purchaseOrder:id,status',
                ])
                ->orderBy('product_variant_id')
                ->orderBy('id'),
        ]);
        $lines = $purchaseOrder->items->mapWithKeys(fn (PurchaseOrderItem $item): array => [
            $item->id => [
                'accepted' => $item->acceptedQuantity(),
                'transferred' => $item->transferredQuantity(),
                'outstanding' => $item->outstandingQuantity(),
            ],
        ]);
        $hasTransferActivity = $purchaseOrder->items->contains(fn (PurchaseOrderItem $item): bool => $item->outgoingTransfer !== null || $item->incomingTransfer !== null
        );
        $canEdit = $admin && $purchaseOrder->isEditable() && ! $hasTransferActivity;
        $canFollowUp = $admin
            && in_array($purchaseOrder->status, PurchaseOrder::OPEN_STATUSES, true)
            && $purchaseOrder->items->contains(fn (PurchaseOrderItem $item): bool => $item->outgoingTransfer === null
                && bccomp($lines[$item->id]['outstanding'], '0.000', 3) > 0
            );
        $canReceive = in_array($purchaseOrder->status, PurchaseOrder::OPEN_STATUSES, true)
            && $lines->contains(fn (array $line): bool => bccomp($line['outstanding'], '0.000', 3) > 0);

        $receiptColumns = ['id', 'purchase_order_id', 'recorded_by', 'reference_text', 'created_at'];
        $receiptItemColumns = ['id', 'restock_id', 'purchase_order_item_id', 'product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot', 'quantity'];
        $damageColumns = ['id', 'restock_id', 'purchase_order_item_id', 'product_name_snapshot', 'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot', 'damaged_quantity', 'damage_note'];
        if ($admin) {
            $receiptColumns[] = 'total_cost';
            $receiptItemColumns[] = 'unit_cost';
            $receiptItemColumns[] = 'line_total';
        }
        $receipts = $purchaseOrder->restocks()
            ->select($receiptColumns)
            ->with([
                'recordedBy:id,name',
                'items' => fn ($query) => $query->select($receiptItemColumns)->orderBy('id'),
                'damageItems' => fn ($query) => $query->select($damageColumns)->orderBy('id'),
            ])
            ->orderBy('id')
            ->get();

        return view('purchase-orders.show', compact(
            'purchaseOrder',
            'admin',
            'lines',
            'canEdit',
            'canFollowUp',
            'canReceive',
            'receipts',
        ));
    }

    private function authorizeOperationalAccess(Request $request): bool
    {
        $role = $request->user()?->role;
        abort_unless(in_array($role, [User::ROLE_ADMIN, 'staff'], true), 403);

        return $role === User::ROLE_ADMIN;
    }

    /**
     * @param  Collection<int, ProductVariant>  $eligible
     * @return array{Collection<int, array{
     *     product_variant_id: int,
     *     product_name: string,
     *     identity: string,
     *     unit: string,
     *     quantity_mode: string,
     *     ordered_quantity: string,
     *     expected_unit_cost: string,
     *     historical: bool,
     *     currently_available_for_new_ordering: bool
     * }>, int}
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

            $rows->push($this->variantDraftRow(
                $variant,
                is_string($item['ordered_quantity'] ?? null) ? $item['ordered_quantity'] : '',
                is_string($item['expected_unit_cost'] ?? null) ? $item['expected_unit_cost'] : '',
            ));
        }

        return [$rows, $removed];
    }

    /**
     * @return array{Collection<int, ProductVariant>, Collection<int, ProductVariant>, Collection<int, ProductVariant>, Collection<int, ProductVariant>}
     */
    private function selectionData(
        LowStockPurchaseOrderRecommendations $recommendations,
        ProcurementVariantCatalogQuery $catalog,
    ): array {
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

        return [
            $uncovered,
            $covered,
            $otherVariants,
            $priorities->concat($otherVariants)->keyBy('id'),
        ];
    }

    /**
     * @param  Collection<int, PurchaseOrderItem>  $persistedItems
     * @param  Collection<int, ProductVariant>  $persistedVariants
     * @param  Collection<int, ProductVariant>  $eligible
     * @return array{Collection<int, array<string, mixed>>, int}
     */
    private function oldEditDraftRows(
        mixed $oldItems,
        Collection $persistedItems,
        Collection $persistedVariants,
        Collection $eligible,
    ): array {
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
            if ($id === false) {
                $removed++;

                continue;
            }

            $quantity = is_string($item['ordered_quantity'] ?? null) ? $item['ordered_quantity'] : '';
            $cost = is_string($item['expected_unit_cost'] ?? null) ? $item['expected_unit_cost'] : '';
            $persistedItem = $persistedItems->get((int) $id);
            if ($persistedItem instanceof PurchaseOrderItem) {
                $rows->push($this->historicalDraftRow(
                    $persistedItem,
                    $persistedVariants->get((int) $id),
                    $eligible->has((int) $id),
                    $quantity,
                    $cost,
                ));

                continue;
            }

            $variant = $eligible->get((int) $id);
            if ($variant instanceof ProductVariant) {
                $rows->push($this->variantDraftRow($variant, $quantity, $cost));

                continue;
            }

            $removed++;
        }

        return [$rows, $removed];
    }

    /** @return array<string, mixed> */
    private function variantDraftRow(ProductVariant $variant, string $quantity, string $cost): array
    {
        return [
            'product_variant_id' => (int) $variant->getKey(),
            'product_name' => $variant->product->name,
            'identity' => $this->identity($variant->size, $variant->type_series, $variant->thickness),
            'unit' => $variant->unit,
            'quantity_mode' => $variant->quantity_mode,
            'ordered_quantity' => $quantity,
            'expected_unit_cost' => $cost,
            'historical' => false,
            'currently_available_for_new_ordering' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function historicalDraftRow(
        PurchaseOrderItem $item,
        ?ProductVariant $variant,
        bool $currentlyAvailable,
        ?string $quantity = null,
        ?string $cost = null,
    ): array {
        return [
            'product_variant_id' => (int) $item->product_variant_id,
            'product_name' => $item->product_name_snapshot,
            'identity' => $this->identity($item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot),
            'unit' => $item->unit_snapshot,
            'quantity_mode' => $variant?->quantity_mode ?? 'fractional',
            'ordered_quantity' => $quantity ?? (string) $item->ordered_quantity,
            'expected_unit_cost' => $cost ?? (string) $item->expected_unit_cost,
            'historical' => true,
            'currently_available_for_new_ordering' => $currentlyAvailable,
        ];
    }

    private function identity(?string $size, ?string $typeSeries, ?string $thickness): string
    {
        return collect([$size, $typeSeries, $thickness])
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->join(' · ') ?: 'Standard';
    }
}

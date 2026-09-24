<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFollowUpPurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Procurement\CreateFollowUpPurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderFollowUpController extends Controller
{
    public function create(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items' => fn ($query) => $query
            ->with('outgoingTransfer:id,source_purchase_order_item_id,quantity')
            ->orderBy('id')]);
        $lines = $purchaseOrder->items->mapWithKeys(fn (PurchaseOrderItem $item): array => [
            $item->id => [
                'accepted' => $item->acceptedQuantity(),
                'transferred' => $item->transferredQuantity(),
                'outstanding' => $item->outstandingQuantity(),
            ],
        ]);
        $eligibleItems = $purchaseOrder->items->filter(fn (PurchaseOrderItem $item): bool => $item->outgoingTransfer === null
            && bccomp($lines[$item->id]['outstanding'], '0.000', 3) > 0
        )->values();
        abort_unless(in_array($purchaseOrder->status, PurchaseOrder::OPEN_STATUSES, true)
            && $eligibleItems->isNotEmpty(), 409, 'This Purchase Order has no quantity eligible for a follow-up.');

        $oldToken = $request->session()->getOldInput('submission_token');
        $submissionToken = is_string($oldToken) && Str::isUuid($oldToken)
            ? strtolower($oldToken)
            : Str::uuid()->toString();
        $supplierName = $request->session()->getOldInput('supplier_name', $purchaseOrder->supplier_name);
        $notesValue = $request->session()->getOldInput('notes', '');

        return view('purchase-orders.follow-up', compact(
            'purchaseOrder',
            'eligibleItems',
            'lines',
            'submissionToken',
            'supplierName',
            'notesValue',
        ));
    }

    public function store(
        CreateFollowUpPurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        CreateFollowUpPurchaseOrder $createFollowUpPurchaseOrder,
    ): RedirectResponse {
        try {
            $child = $createFollowUpPurchaseOrder->execute(
                $request->user(),
                $purchaseOrder,
                $request->validated('submission_token'),
                $request->validated('supplier_name'),
                $request->validated('notes'),
                $request->selectedItems(),
            );
        } catch (ValidationException $exception) {
            $purchaseOrder->refresh();
            $purchaseOrder->load('items.outgoingTransfer');
            $freshFollowUpPossible = in_array($purchaseOrder->status, PurchaseOrder::OPEN_STATUSES, true)
                && $purchaseOrder->items->contains(fn (PurchaseOrderItem $item): bool => $item->outgoingTransfer === null
                    && bccomp($item->outstandingQuantity(), '0.000', 3) > 0
                );
            $route = $freshFollowUpPossible
                ? route('purchase-orders.follow-up.create', $purchaseOrder)
                : route('purchase-orders.show', $purchaseOrder);

            return redirect($route)
                ->withErrors($exception->errors())
                ->withInput($request->except('_token'));
        }

        return redirect()->route('purchase-orders.show', $child)->with('purchase_order_confirmation', [
            'id' => $child->getKey(),
            'supplier_name' => $child->supplier_name,
            'status' => $child->status,
            'line_count' => $child->items->count(),
            'replayed' => ! $child->wasRecentlyCreated,
        ]);
    }
}

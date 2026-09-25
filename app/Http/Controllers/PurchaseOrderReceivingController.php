<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\Procurement\ReceivePurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderReceivingController extends Controller
{
    public function create(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $this->authorizeOperationalAccess($request);
        $purchaseOrder->load(['items' => fn ($query) => $query->select([
            'id', 'purchase_order_id', 'product_variant_id', 'product_name_snapshot',
            'size_snapshot', 'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot', 'ordered_quantity',
        ])->orderBy('id')]);
        $lines = $purchaseOrder->items->mapWithKeys(fn (PurchaseOrderItem $item): array => [
            $item->id => ['accepted' => $item->acceptedQuantity(), 'outstanding' => $item->outstandingQuantity()],
        ]);
        $outstandingItems = $purchaseOrder->items->filter(
            fn (PurchaseOrderItem $item): bool => bccomp($lines[$item->id]['outstanding'], '0.000', 3) > 0,
        );
        abort_unless(in_array($purchaseOrder->status, PurchaseOrder::OPEN_STATUSES, true) && $outstandingItems->isNotEmpty(), 409, 'This Purchase Order has no open receiving quantity.');

        $oldToken = $request->session()->getOldInput('submission_token');
        $submissionToken = is_string($oldToken) && Str::isUuid($oldToken)
            ? strtolower($oldToken)
            : Str::uuid()->toString();

        return view('purchase-orders.receive', compact('purchaseOrder', 'outstandingItems', 'lines', 'submissionToken'));
    }

    public function store(
        ReceivePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        ReceivePurchaseOrder $receivePurchaseOrder,
    ): RedirectResponse {
        try {
            $receipt = $receivePurchaseOrder->execute(
                $request->user(),
                $purchaseOrder,
                $request->validated('submission_token'),
                $request->validated('reference_text'),
                $request->validated('notes'),
                $request->receiptItems(),
            );
        } catch (ValidationException $exception) {
            $purchaseOrder->refresh();
            if (! in_array($purchaseOrder->status, PurchaseOrder::OPEN_STATUSES, true)) {
                return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors($exception->errors());
            }

            throw $exception;
        }

        $message = $receipt->wasRecentlyCreated ? 'Purchase Order receipt recorded.' : 'This receipt was already recorded.';

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', $message);
    }

    private function authorizeOperationalAccess(Request $request): void
    {
        abort_unless($request->user()?->isActive()
            && in_array($request->user()->role, [User::ROLE_ADMIN, 'staff'], true), 403);
    }
}

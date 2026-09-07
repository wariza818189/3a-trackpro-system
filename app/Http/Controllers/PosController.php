<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\ProductVariant;
use App\Services\Sales\RecordSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $variants = ProductVariant::query()
            ->select(['id', 'product_id', 'size', 'type_series', 'thickness', 'unit', 'quantity_mode', 'current_stock', 'selling_price', 'status'])
            ->with(['product:id,category_id,name,status', 'product.category:id,name,status'])
            ->inActiveHierarchy()
            ->whereHas('openingInventoryMovements')
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        $oldToken = $request->session()->getOldInput('submission_token');
        $tokenMisuse = (bool) $request->session()->get('checkout_token_misused', false);
        $submissionToken = ! $tokenMisuse && is_string($oldToken) && Str::isUuid($oldToken)
            ? strtolower($oldToken)
            : Str::uuid()->toString();

        [$cartRows, $pricesRefreshed, $unavailableItems] = $this->rebuildCart(
            $request->session()->getOldInput('items', []),
            $variants->keyBy('id'),
        );

        return view('pos.index', compact(
            'variants', 'submissionToken', 'cartRows', 'pricesRefreshed', 'unavailableItems', 'tokenMisuse',
        ));
    }

    public function checkout(StoreSaleRequest $request, RecordSale $recordSale): RedirectResponse
    {
        try {
            $sale = $recordSale->execute(
                $request->user(),
                $request->validated('submission_token'),
                $request->validated('amount_tendered'),
                $request->validated('items'),
            );
        } catch (ValidationException $exception) {
            $semanticReuse = collect($exception->errors()['submission_token'] ?? [])
                ->contains(fn (string $message): bool => str_contains($message, 'already associated'));
            if (! $semanticReuse) {
                throw $exception;
            }

            return redirect()->route('pos.index')
                ->withErrors($exception->errors())
                ->withInput()
                ->with('checkout_token_misused', true);
        }

        return redirect()->route('pos.index')->with('sale_confirmation', [
            'message' => $sale->wasRecentlyCreated ? 'Sale completed.' : 'Sale was already recorded.',
            'receipt_number' => $sale->receiptNumber(),
            'total' => (string) $sale->total_amount,
            'cash' => (string) $sale->cash_received,
            'change' => (string) $sale->change_amount,
            'item_count' => $sale->items->count(),
        ]);
    }

    /** @return array{list<array<string, mixed>>, bool, list<string>} */
    private function rebuildCart(mixed $oldItems, $variants): array
    {
        if (! is_array($oldItems)) {
            return [[], false, []];
        }
        $rows = [];
        $refreshed = false;
        $unavailable = [];
        foreach ($oldItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = filter_var($item['product_variant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || ! $variants->has((int) $id)) {
                if ($id !== false) {
                    $unavailable[] = "Variant #{$id} is no longer available and was removed from the cart.";
                }

                continue;
            }
            $variant = $variants->get((int) $id);
            $current = (string) $variant->selling_price;
            $oldExpected = $this->canonicalPrice($item['expected_unit_price'] ?? null);
            if ($oldExpected !== null && bccomp($oldExpected, $current, 2) !== 0) {
                $refreshed = true;
            }
            $rows[] = [
                'product_variant_id' => (int) $id,
                'quantity' => is_string($item['quantity'] ?? null) ? trim($item['quantity']) : '',
                'expected_unit_price' => $current,
            ];
        }

        return [$rows, $refreshed, $unavailable];
    }

    private function canonicalPrice(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/D', trim($value)) !== 1) {
            return null;
        }
        [$integer, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');

        return (ltrim($integer, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesHistoryController extends Controller
{
    public function index(Request $request): View
    {
        [$receipt, $receiptId, $receiptError] = $this->normalizeReceipt($request->query('receipt'));
        [$cashier, $cashierId, $cashierError] = $this->normalizePositiveInteger(
            $request->query('cashier'),
            'Select a valid cashier.',
        );
        [$dateFrom, $fromBoundary, $dateFromError] = $this->normalizeDate(
            $request->query('date_from'),
            'Enter a valid from date in YYYY-MM-DD format.',
        );
        [$dateTo, $toDate, $dateToError] = $this->normalizeDate(
            $request->query('date_to'),
            'Enter a valid to date in YYYY-MM-DD format.',
        );

        $filterErrors = array_filter([
            'receipt' => $receiptError,
            'cashier' => $cashierError,
            'date_from' => $dateFromError,
            'date_to' => $dateToError,
        ]);

        if ($fromBoundary !== null && $toDate !== null && $fromBoundary->isAfter($toDate)) {
            $filterErrors['date_to'] = 'The to date must be on or after the from date.';
        }

        $sales = Sale::query()
            ->select(['id', 'recorded_by', 'status', 'total_amount', 'cash_received', 'change_amount', 'created_at'])
            ->with('recordedBy:id,name')
            ->withCount('items')
            ->when($filterErrors !== [], fn (Builder $query): Builder => $query->whereKey(-1))
            ->when($filterErrors === [] && $receiptId !== null, fn (Builder $query): Builder => $query->whereKey($receiptId))
            ->when($filterErrors === [] && $cashierId !== null, fn (Builder $query): Builder => $query->where('recorded_by', $cashierId))
            ->when($filterErrors === [] && $fromBoundary !== null, fn (Builder $query): Builder => $query->where('created_at', '>=', $fromBoundary))
            ->when($filterErrors === [] && $toDate !== null, fn (Builder $query): Builder => $query->where('created_at', '<', $toDate->addDay()->startOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $cashiers = User::query()
            ->select(['id', 'name'])
            ->whereIn('id', Sale::query()->select('recorded_by')->distinct())
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('sales.index', compact(
            'sales', 'cashiers', 'receipt', 'cashier', 'dateFrom', 'dateTo', 'filterErrors',
        ));
    }

    public function show(string $sale): View
    {
        [, $saleId] = $this->normalizePositiveInteger($sale, '');
        abort_if($saleId === null, 404);

        $record = Sale::query()
            ->select(['id', 'recorded_by', 'status', 'total_amount', 'cash_received', 'change_amount', 'created_at'])
            ->with('recordedBy:id,name')
            ->findOrFail($saleId);

        $items = $record->items()
            ->select([
                'id', 'sale_id', 'product_name_snapshot', 'size_snapshot', 'type_series_snapshot',
                'thickness_snapshot', 'unit_snapshot', 'quantity', 'unit_price', 'line_total',
            ])
            ->orderBy('id')
            ->get();
        $record->setRelation('items', $items);

        return view('sales.show', ['sale' => $record]);
    }

    /** @return array{string, int|null, string|null} */
    private function normalizeReceipt(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['', null, null];
        }
        if (! is_string($value)) {
            return ['', null, 'Enter a receipt number such as TRX-000002.'];
        }

        $receipt = strtoupper(trim($value));
        if (preg_match('/\ATRX-(\d{6,})\z/D', $receipt, $matches) !== 1) {
            return [$receipt, null, 'Enter a receipt number such as TRX-000002.'];
        }

        $digits = ltrim($matches[1], '0');
        if ($digits === '' || ! $this->fitsPositiveInteger($digits)) {
            return [$receipt, null, 'Enter a receipt number such as TRX-000002.'];
        }

        $id = (int) $digits;
        $canonical = 'TRX-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        if ($receipt !== $canonical) {
            return [$receipt, null, 'Enter the complete canonical receipt number.'];
        }

        return [$receipt, $id, null];
    }

    /** @return array{string, int|null, string|null} */
    private function normalizePositiveInteger(mixed $value, string $message): array
    {
        if ($value === null || $value === '') {
            return ['', null, null];
        }
        if (! is_string($value)) {
            return ['', null, $message];
        }

        $normalized = trim($value);
        if (preg_match('/\A[1-9]\d*\z/D', $normalized) !== 1 || ! $this->fitsPositiveInteger($normalized)) {
            return [$normalized, null, $message];
        }

        return [$normalized, (int) $normalized, null];
    }

    /** @return array{string, CarbonImmutable|null, string|null} */
    private function normalizeDate(mixed $value, string $message): array
    {
        if ($value === null || $value === '') {
            return ['', null, null];
        }
        if (! is_string($value)) {
            return ['', null, $message];
        }

        $normalized = trim($value);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $normalized) !== 1) {
            return [$normalized, null, $message];
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $normalized, config('app.timezone'));
        $dateErrors = CarbonImmutable::getLastErrors();
        if ($date === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $date->format('Y-m-d') !== $normalized) {
            return [$normalized, null, $message];
        }

        return [$normalized, $date->startOfDay(), null];
    }

    private function fitsPositiveInteger(string $digits): bool
    {
        $maximum = (string) PHP_INT_MAX;

        return strlen($digits) < strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) <= 0);
    }
}

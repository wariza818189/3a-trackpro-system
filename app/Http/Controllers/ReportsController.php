<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use LogicException;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $datesSubmitted = $request->query->has('date_from') || $request->query->has('date_to');

        if ($datesSubmitted) {
            [$dateFrom, $from, $fromError] = $this->date(
                $request->query('date_from'),
                'Enter a valid from date in YYYY-MM-DD format.',
            );
            [$dateTo, $to, $toError] = $this->date(
                $request->query('date_to'),
                'Enter a valid to date in YYYY-MM-DD format.',
            );
        } else {
            $from = $today->subDays(6);
            $to = $today;
            $dateFrom = $from->format('Y-m-d');
            $dateTo = $to->format('Y-m-d');
            $fromError = null;
            $toError = null;
        }

        [$cashier, $cashierId, $cashierError] = $this->positiveInteger(
            $request->query('cashier'),
            'Select a valid cashier.',
        );

        $filterErrors = array_filter([
            'date_from' => $fromError,
            'date_to' => $toError,
            'cashier' => $cashierError,
        ]);

        if ($from !== null && $to !== null && $from->isAfter($to)) {
            $filterErrors['date_to'] = 'The to date must be on or after the from date.';
        } elseif ($from !== null && $to !== null && $from->diff($to)->days > 365) {
            $filterErrors['date_to'] = 'The report range must not exceed 366 calendar days.';
        }

        $cashiers = User::query()
            ->select(['id', 'name'])
            ->whereIn('id', Sale::query()->select('recorded_by')->distinct())
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $salesTotal = '0.00';
        $transactionCount = 0;
        $dailySales = collect();
        $quantitiesByUnit = collect();

        if ($filterErrors === [] && $from !== null && $to !== null) {
            $end = $to->addDay()->startOfDay();
            $summary = $this->qualifyingSales($from, $end, $cashierId)
                ->selectRaw('CAST(COALESCE(SUM(sales.total_amount), 0) AS CHAR) as sales_total')
                ->selectRaw('COUNT(*) as transaction_count')
                ->firstOrFail();
            $salesTotal = $this->decimal((string) $summary->sales_total, 2);
            $transactionCount = (int) $summary->transaction_count;

            $dailyAggregates = $this->qualifyingSales($from, $end, $cashierId)
                ->selectRaw('DATE(sales.created_at) as sale_date')
                ->selectRaw('COUNT(*) as transaction_count')
                ->selectRaw('CAST(COALESCE(SUM(sales.total_amount), 0) AS CHAR) as sales_total')
                ->groupByRaw('DATE(sales.created_at)')
                ->get()
                ->keyBy(fn (object $row): string => (string) $row->sale_date);
            $dailySales = $this->dailyRows($from, $to, $dailyAggregates);

            $quantitiesByUnit = SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.status', Sale::STATUS_COMPLETED)
                ->where('sales.created_at', '>=', $from)
                ->where('sales.created_at', '<', $end)
                ->when($cashierId !== null, fn (Builder $query): Builder => $query->where('sales.recorded_by', $cashierId))
                ->select('sale_items.unit_snapshot')
                ->selectRaw('CAST(COALESCE(SUM(sale_items.quantity), 0) AS CHAR) as quantity_total')
                ->groupBy('sale_items.unit_snapshot')
                ->get()
                ->map(fn (SaleItem $row): array => [
                    'unit' => (string) $row->unit_snapshot,
                    'quantity_total' => $this->decimal((string) $row->quantity_total, 3),
                ])
                ->sortBy(fn (array $row): string => sprintf(
                    '%03d-%s',
                    ($position = array_search($row['unit'], ProductVariant::SUPPORTED_UNITS, true)) === false ? 999 : $position,
                    $row['unit'],
                ))
                ->values();
        }

        return view('reports.index', compact(
            'dateFrom', 'dateTo', 'cashier', 'cashiers', 'filterErrors', 'salesTotal',
            'transactionCount', 'dailySales', 'quantitiesByUnit',
        ));
    }

    private function qualifyingSales(CarbonImmutable $from, CarbonImmutable $end, ?int $cashierId): Builder
    {
        return Sale::query()
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.created_at', '>=', $from)
            ->where('sales.created_at', '<', $end)
            ->when($cashierId !== null, fn (Builder $query): Builder => $query->where('sales.recorded_by', $cashierId));
    }

    /** @param Collection<string, object> $aggregates */
    private function dailyRows(CarbonImmutable $from, CarbonImmutable $to, Collection $aggregates): Collection
    {
        $rows = collect();
        for ($date = $to; ! $date->isBefore($from); $date = $date->subDay()) {
            $aggregate = $aggregates->get($date->format('Y-m-d'));
            $rows->push([
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('M j, Y'),
                'transaction_count' => (int) ($aggregate?->transaction_count ?? 0),
                'sales_total' => $this->decimal((string) ($aggregate?->sales_total ?? '0'), 2),
            ]);
        }

        return $rows;
    }

    /** @return array{string, CarbonImmutable|null, string|null} */
    private function date(mixed $value, string $message): array
    {
        if (! is_string($value)) {
            return ['', null, $message];
        }

        $normalized = trim($value);
        if ($normalized === '' || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $normalized) !== 1) {
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

    /** @return array{string, int|null, string|null} */
    private function positiveInteger(mixed $value, string $message): array
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

    private function fitsPositiveInteger(string $digits): bool
    {
        $maximum = (string) PHP_INT_MAX;

        return strlen($digits) < strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) <= 0);
    }

    private function decimal(string $value, int $scale): string
    {
        $value = trim($value);
        if (preg_match('/\A\d+(?:\.\d+)?\z/D', $value) !== 1) {
            throw new LogicException('A reporting aggregate returned an invalid decimal value.');
        }

        return bcadd($value, '0', $scale);
    }
}

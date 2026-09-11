<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $tomorrow = $today->addDay();

        $todaySales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow)
            ->selectRaw('CAST(COALESCE(SUM(total_amount), 0) AS CHAR) as sales_total')
            ->selectRaw('COUNT(*) as transaction_count')
            ->firstOrFail();

        $stockCounts = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('categories.status', Category::STATUS_ACTIVE)
            ->where('products.status', Product::STATUS_ACTIVE)
            ->where('product_variants.status', ProductVariant::STATUS_ACTIVE)
            ->selectRaw('SUM(CASE WHEN product_variants.current_stock <= product_variants.low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count')
            ->selectRaw('SUM(CASE WHEN product_variants.current_stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count')
            ->first();

        $recentSales = Sale::query()
            ->select(['id', 'recorded_by', 'status', 'total_amount', 'created_at'])
            ->with('recordedBy:id,name')
            ->withCount('items')
            ->where('status', Sale::STATUS_COMPLETED)
            ->latest('created_at')
            ->latest('id')
            ->limit(5)
            ->get();

        $lowStockItems = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('categories.status', Category::STATUS_ACTIVE)
            ->where('products.status', Product::STATUS_ACTIVE)
            ->where('product_variants.status', ProductVariant::STATUS_ACTIVE)
            ->whereColumn('product_variants.current_stock', '<=', 'product_variants.low_stock_threshold')
            ->select([
                'product_variants.id', 'product_variants.size', 'product_variants.type_series',
                'product_variants.thickness', 'product_variants.unit', 'product_variants.quantity_mode',
                'product_variants.current_stock',
                'product_variants.low_stock_threshold', 'products.name as product_name',
                'categories.name as category_name',
            ])
            ->orderByRaw('CASE WHEN product_variants.current_stock = 0 THEN 0 ELSE 1 END')
            ->orderBy('categories.name')
            ->orderBy('products.name')
            ->orderBy('product_variants.size')
            ->orderBy('product_variants.type_series')
            ->orderBy('product_variants.thickness')
            ->orderBy('product_variants.unit')
            ->orderBy('product_variants.id')
            ->limit(5)
            ->get();

        $sevenDayTrend = $request->user()->isAdmin()
            ? $this->sevenDayTrend($today, $tomorrow)
            : null;

        return view('dashboard.index', [
            'today' => $today,
            'todaySalesTotal' => $this->decimal((string) $todaySales->sales_total, 2),
            'todayTransactionCount' => (int) $todaySales->transaction_count,
            'lowStockCount' => (int) ($stockCounts?->low_stock_count ?? 0),
            'outOfStockCount' => (int) ($stockCounts?->out_of_stock_count ?? 0),
            'recentSales' => $recentSales,
            'lowStockItems' => $lowStockItems,
            'sevenDayTrend' => $sevenDayTrend,
        ]);
    }

    /** @return list<array{date: string, label: string, transaction_count: int, sales_total: string, bar_width: int}> */
    private function sevenDayTrend(CarbonImmutable $today, CarbonImmutable $tomorrow): array
    {
        $start = $today->subDays(6);
        $aggregates = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $tomorrow)
            ->selectRaw('DATE(created_at) as sale_date')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('CAST(COALESCE(SUM(total_amount), 0) AS CHAR) as sales_total')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->sale_date);

        $trend = [];
        $maximum = '0.00';
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $start->addDays($offset);
            $aggregate = $aggregates->get($date->format('Y-m-d'));
            $total = $this->decimal((string) ($aggregate?->sales_total ?? '0'), 2);
            if (bccomp($total, $maximum, 2) === 1) {
                $maximum = $total;
            }
            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('M j'),
                'transaction_count' => (int) ($aggregate?->transaction_count ?? 0),
                'sales_total' => $total,
                'bar_width' => 0,
            ];
        }

        if (bccomp($maximum, '0.00', 2) === 1) {
            foreach ($trend as &$day) {
                $day['bar_width'] = (int) bcdiv(bcmul($day['sales_total'], '100', 2), $maximum, 0);
            }
            unset($day);
        }

        return $trend;
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

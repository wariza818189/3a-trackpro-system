<?php

namespace App\Http\Controllers;

use App\Queries\Reports\ProductSalesReportQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductSalesReportController extends Controller
{
    public function index(Request $request, ProductSalesReportQuery $report): View
    {
        [$dateFrom, $dateTo, $from, $to, $filterErrors] = ReportsController::reportDates($request);
        $rows = collect();

        if ($filterErrors === [] && $from !== null && $to !== null) {
            $rows = $report->get($from, $to->addDay()->startOfDay());
        }

        return view('reports.product-sales', compact('dateFrom', 'dateTo', 'filterErrors', 'rows'));
    }
}

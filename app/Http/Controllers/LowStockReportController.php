<?php

namespace App\Http\Controllers;

use App\Queries\Reports\LowStockReportQuery;
use Illuminate\View\View;

final class LowStockReportController extends Controller
{
    public function index(LowStockReportQuery $report): View
    {
        return view('reports.low-stock', ['variants' => $report->get()]);
    }
}

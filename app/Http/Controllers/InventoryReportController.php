<?php

namespace App\Http\Controllers;

use App\Queries\Reports\InventoryReportQuery;
use Illuminate\View\View;

final class InventoryReportController extends Controller
{
    public function index(InventoryReportQuery $report): View
    {
        return view('reports.inventory', ['variants' => $report->get()]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Queries\Reports\RestockingReportQuery;
use Illuminate\View\View;

final class RestockingReportController extends Controller
{
    public function index(RestockingReportQuery $report): View
    {
        return view('reports.restocking', ['items' => $report->get()]);
    }
}

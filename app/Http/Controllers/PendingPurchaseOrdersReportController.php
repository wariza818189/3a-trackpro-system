<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Queries\Reports\PendingPurchaseOrdersReportQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PendingPurchaseOrdersReportController extends Controller
{
    public function index(Request $request, PendingPurchaseOrdersReportQuery $report): View
    {
        $supplierInput = $request->query('supplier', '');
        $statusInput = $request->query('status', '');
        $supplier = is_string($supplierInput) ? trim($supplierInput) : '';
        $status = is_string($statusInput) ? $statusInput : '';

        $filterErrors = [];
        if (! is_string($supplierInput) || mb_strlen($supplier) > 150) {
            $filterErrors['supplier'] = 'Enter a valid supplier search of at most 150 characters.';
        }
        if (! is_string($statusInput) || ($status !== '' && ! in_array($status, PurchaseOrder::OPEN_STATUSES, true))) {
            $filterErrors['status'] = 'Select a valid open status.';
        }

        $orders = $filterErrors === [] ? $report->get($supplier, $status === '' ? null : $status) : collect();

        return view('reports.pending-purchase-orders', compact('supplier', 'status', 'filterErrors', 'orders'));
    }
}

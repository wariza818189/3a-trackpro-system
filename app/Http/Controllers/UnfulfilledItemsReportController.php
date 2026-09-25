<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Queries\Reports\UnfulfilledItemsReportQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UnfulfilledItemsReportController extends Controller
{
    public function index(Request $request, UnfulfilledItemsReportQuery $report): View
    {
        $supplierInput = $request->query('supplier', '');
        $itemInput = $request->query('item', '');
        $statusInput = $request->query('status', '');
        $poInput = $request->query('po', '');

        $supplier = is_string($supplierInput) ? trim($supplierInput) : '';
        $itemSearch = is_string($itemInput) ? trim($itemInput) : '';
        $status = is_string($statusInput) ? $statusInput : '';
        $po = is_string($poInput) ? trim($poInput) : '';

        $filterErrors = [];
        if (! is_string($supplierInput) || mb_strlen($supplier) > 150) {
            $filterErrors['supplier'] = 'Enter a valid supplier search of at most 150 characters.';
        }
        if (! is_string($itemInput) || mb_strlen($itemSearch) > 150) {
            $filterErrors['item'] = 'Enter a valid item search of at most 150 characters.';
        }
        if (! is_string($statusInput) || ($status !== '' && ! in_array($status, PurchaseOrder::OPEN_STATUSES, true))) {
            $filterErrors['status'] = 'Select a valid open status.';
        }
        $poId = null;
        if (! is_string($poInput) || ($po !== '' && (! preg_match('/^[1-9][0-9]*$/D', $po) || filter_var($po, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false))) {
            $filterErrors['po'] = 'Enter a valid numeric PO identifier.';
        } elseif ($po !== '') {
            $poId = (int) $po;
        }

        $lines = $filterErrors === []
            ? $report->get($supplier, $itemSearch, $status === '' ? null : $status, $poId)
            : collect();

        return view('reports.unfulfilled-items', compact('supplier', 'itemSearch', 'status', 'po', 'filterErrors', 'lines'));
    }
}

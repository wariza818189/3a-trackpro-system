<?php

namespace App\Http\Controllers;

use App\Queries\Reports\DamagedItemsReportQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DamagedItemsReportController extends Controller
{
    public function index(Request $request, DamagedItemsReportQuery $report): View
    {
        $supplierInput = $request->query('supplier', '');
        $itemInput = $request->query('item', '');
        $poInput = $request->query('po', '');

        $supplier = is_string($supplierInput) ? trim($supplierInput) : '';
        $itemSearch = is_string($itemInput) ? trim($itemInput) : '';
        $po = is_string($poInput) ? trim($poInput) : '';

        $filterErrors = [];
        if (! is_string($supplierInput) || mb_strlen($supplier) > 150) {
            $filterErrors['supplier'] = 'Enter a valid supplier search of at most 150 characters.';
        }
        if (! is_string($itemInput) || mb_strlen($itemSearch) > 150) {
            $filterErrors['item'] = 'Enter a valid item search of at most 150 characters.';
        }
        $poId = null;
        if (! is_string($poInput) || ($po !== '' && (! preg_match('/^[1-9][0-9]*$/D', $po) || filter_var($po, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false))) {
            $filterErrors['po'] = 'Enter a valid numeric PO identifier.';
        } elseif ($po !== '') {
            $poId = (int) $po;
        }

        $damages = $filterErrors === [] ? $report->get($supplier, $itemSearch, $poId) : collect();
        $hasEvidence = $filterErrors === [] && $damages->isEmpty() && $report->hasEvidence();

        return view('reports.damaged-items', compact('supplier', 'itemSearch', 'po', 'filterErrors', 'damages', 'hasEvidence'));
    }
}

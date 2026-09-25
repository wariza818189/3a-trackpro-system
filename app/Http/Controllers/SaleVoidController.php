<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoidSaleRequest;
use App\Models\Sale;
use App\Services\Sales\SaleVoidService;
use Illuminate\Http\RedirectResponse;

class SaleVoidController extends Controller
{
    public function __invoke(
        VoidSaleRequest $request,
        Sale $sale,
        SaleVoidService $saleVoid,
    ): RedirectResponse {
        $saleVoid->execute(
            $request->user(),
            $sale,
            $request->validated('reason'),
        );

        return redirect()->route('sales.show', $sale)->with('success', 'Sale voided successfully.');
    }
}

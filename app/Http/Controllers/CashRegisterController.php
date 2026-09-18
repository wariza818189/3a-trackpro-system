<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpenCashRegisterRequest;
use App\Services\CashRegister\CloseCashRegister;
use App\Services\CashRegister\OpenCashRegister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function open(
        OpenCashRegisterRequest $request,
        OpenCashRegister $openCashRegister,
    ): RedirectResponse {
        $openCashRegister->execute(
            $request->user(),
            $request->validated('opening_cash'),
        );

        return redirect()->route('pos.index')->with('success', 'Cash register opened.');
    }

    public function close(Request $request, CloseCashRegister $closeCashRegister): RedirectResponse
    {
        $closeCashRegister->execute($request->user());

        return redirect()->route('pos.index')->with('success', 'Cash register closed.');
    }
}

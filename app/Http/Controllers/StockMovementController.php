<?php

namespace App\Http\Controllers;

use App\Presenters\Inventory\StockMovementPresenter;
use App\Queries\Inventory\StockMovementQuery;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(StockMovementQuery $query, StockMovementPresenter $presenter): View
    {
        return view('inventory.movements.index', [
            'movements' => $query->history()->through($presenter->present(...)),
        ]);
    }
}

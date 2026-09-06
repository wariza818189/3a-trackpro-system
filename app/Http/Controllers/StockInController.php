<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRestockRequest;
use App\Models\ProductVariant;
use App\Models\Restock;
use App\Services\Inventory\RecordRestock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StockInController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->user()->isAdmin();
        $columns = ['id', 'recorded_by', 'reference_text', 'created_at'];
        if ($admin) {
            $columns[] = 'total_cost';
        }

        $restocks = Restock::query()
            ->select($columns)
            ->with('recordedBy:id,name')
            ->withCount('items')
            ->latest('created_at')
            ->latest('id')
            ->paginate(20);

        return view('stock-in.index', compact('restocks', 'admin'));
    }

    public function create(Request $request): View
    {
        $oldToken = $request->session()->getOldInput('submission_token');
        $submissionToken = is_string($oldToken)
            && preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D', $oldToken) === 1
            ? strtolower($oldToken)
            : Str::uuid()->toString();

        $variants = ProductVariant::query()
            ->select(['id', 'product_id', 'size', 'type_series', 'thickness', 'unit', 'quantity_mode', 'current_stock', 'status'])
            ->with(['product:id,category_id,name,status', 'product.category:id,name,status'])
            ->inActiveHierarchy()
            ->whereHas('openingInventoryMovements')
            ->orderBy('product_id')
            ->orderBy('size')
            ->get();

        return view('stock-in.create', compact('submissionToken', 'variants'));
    }

    public function store(StoreRestockRequest $request, RecordRestock $recordRestock): RedirectResponse
    {
        $restock = $recordRestock->execute(
            $request->user(),
            $request->validated('submission_token'),
            $request->validated('reference_text'),
            $request->validated('notes'),
            $request->validated('items'),
        );

        $message = $restock->wasRecentlyCreated ? 'Stock In recorded.' : 'Stock In was already recorded.';

        return redirect()->route('stock-in.show', $restock->getKey())->with('success', $message);
    }

    public function show(Request $request, string $restock): View
    {
        abort_unless(ctype_digit($restock) && (int) $restock > 0, 404);
        $admin = $request->user()->isAdmin();
        $headerColumns = ['id', 'recorded_by', 'reference_text', 'notes', 'created_at'];
        $itemColumns = [
            'id', 'restock_id', 'product_variant_id', 'product_name_snapshot', 'size_snapshot',
            'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot', 'quantity', 'created_at',
        ];
        if ($admin) {
            $headerColumns[] = 'total_cost';
            $itemColumns[] = 'unit_cost';
            $itemColumns[] = 'line_total';
        }

        $record = Restock::query()
            ->select($headerColumns)
            ->with('recordedBy:id,name')
            ->findOrFail((int) $restock);
        $items = $record->items()
            ->select($itemColumns)
            ->with(['stockMovement' => fn ($query) => $query->select([
                'id', 'restock_item_id', 'quantity_before', 'quantity_change', 'quantity_after', 'performed_by', 'created_at',
            ])])
            ->orderBy('id')
            ->get();
        $record->setRelation('items', $items);

        return view('stock-in.show', ['restock' => $record, 'admin' => $admin]);
    }
}

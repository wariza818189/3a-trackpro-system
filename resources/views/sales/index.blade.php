@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Completed transactions</p>
        <h1 class="mt-1 text-3xl font-bold">Sales History</h1>
        <p class="mt-2 text-slate-600">Find immutable sale records and reprint their receipts.</p>
    </div>

    <form method="GET" action="{{ route('sales.index') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-5">
        <label>
            <span class="text-sm font-medium">Receipt number</span>
            <input name="receipt" value="{{ $receipt }}" placeholder="TRX-000002" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            @if (isset($filterErrors['receipt']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['receipt'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Cashier</span>
            <select name="cashier" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All cashiers</option>
                @foreach ($cashiers as $cashierOption)
                    <option value="{{ $cashierOption->id }}" @selected($cashier === (string) $cashierOption->id)>{{ $cashierOption->name }}</option>
                @endforeach
            </select>
            @if (isset($filterErrors['cashier']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['cashier'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Date from</span>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            @if (isset($filterErrors['date_from']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_from'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Date to</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            @if (isset($filterErrors['date_to']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_to'] }}</span>@endif
        </label>
        <div class="flex items-end gap-2">
            <button class="flex-1 rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-500">Apply filters</button>
            <a href="{{ route('sales.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-100">Clear</a>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-sm">Receipt</th>
                        <th class="px-3 py-3 text-left text-sm">Date / time</th>
                        <th class="px-3 py-3 text-left text-sm">Cashier</th>
                        <th class="px-3 py-3 text-left text-sm">Status</th>
                        <th class="px-3 py-3 text-right text-sm">Distinct items</th>
                        <th class="px-3 py-3 text-right text-sm">Total</th>
                        <th class="px-3 py-3 text-right text-sm">Cash received</th>
                        <th class="px-3 py-3 text-right text-sm">Change</th>
                        <th class="px-3 py-3 text-right text-sm">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="px-3 py-3 font-semibold">{{ $sale->receiptNumber() }}</td>
                            <td class="px-3 py-3 text-sm">{{ $sale->created_at?->format('M j, Y g:i A') }}</td>
                            <td class="px-3 py-3 text-sm">{{ $sale->recordedBy->name }}</td>
                            <td class="px-3 py-3 text-sm"><span @class(['rounded-full px-2 py-1 text-xs font-semibold capitalize', 'bg-emerald-100 text-emerald-800' => $sale->status === \App\Models\Sale::STATUS_COMPLETED, 'bg-red-100 text-red-800' => $sale->status !== \App\Models\Sale::STATUS_COMPLETED])>{{ $sale->status }}</span></td>
                            <td class="px-3 py-3 text-right text-sm">{{ $sale->items_count }}</td>
                            <td class="px-3 py-3 text-right text-sm">₱{{ $sale->total_amount }}</td>
                            <td class="px-3 py-3 text-right text-sm">₱{{ $sale->cash_received }}</td>
                            <td class="px-3 py-3 text-right text-sm">₱{{ $sale->change_amount }}</td>
                            <td class="px-3 py-3 text-right"><a href="{{ route('sales.show', $sale->id) }}" class="rounded border border-amber-400 px-3 py-1.5 text-sm font-medium text-amber-800">View receipt</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">No Sales found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $sales->links() }}</div>
</main>
@endsection

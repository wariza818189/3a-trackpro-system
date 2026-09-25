@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
    <h1 class="mt-1 text-3xl font-bold">Damaged Items Report</h1>
    <p class="mt-2 text-slate-600">Each row records one damaged Purchase Order item in one receipt. Newest receipts appear first.</p>
    <a href="{{ route('reports.index') }}" class="mt-3 inline-block font-semibold text-amber-700 print:hidden">Back to Reports</a>

    <form method="GET" action="{{ route('reports.damaged-items') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_11rem_auto] print:hidden">
        <label><span class="text-sm font-medium">Supplier</span><input name="supplier" value="{{ $supplier }}" maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Search supplier">@if (isset($filterErrors['supplier']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['supplier'] }}</span>@endif</label>
        <label><span class="text-sm font-medium">Item</span><input name="item" value="{{ $itemSearch }}" maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Search item snapshots">@if (isset($filterErrors['item']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['item'] }}</span>@endif</label>
        <label><span class="text-sm font-medium">PO #</span><input name="po" value="{{ $po }}" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Exact ID">@if (isset($filterErrors['po']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['po'] }}</span>@endif</label>
        <div class="flex items-end gap-2"><button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-500">Apply filters</button><a href="{{ route('reports.damaged-items') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Reset</a></div>
    </form>

    @if ($filterErrors !== [])
        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">The report was not run because one or more filters are invalid.</p>
    @else
        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th scope="col" class="px-3 py-3 text-left">Item</th><th scope="col" class="px-3 py-3 text-left">PO</th><th scope="col" class="px-3 py-3 text-left">Supplier</th><th scope="col" class="px-3 py-3 text-left">Receipt</th><th scope="col" class="px-3 py-3 text-left">Recorded by / time</th><th scope="col" class="px-3 py-3 text-right">Damaged quantity</th><th scope="col" class="px-3 py-3 text-left">Damage note</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($damages as $damage)
                        @php($receipt = $damage->restock)
                        @php($order = $receipt->purchaseOrder)
                        <tr data-report-damage="{{ $damage->id }}">
                            <td class="px-3 py-3"><span class="font-semibold">{{ $damage->product_name_snapshot }}</span><span class="block text-slate-600">{{ collect([$damage->size_snapshot, $damage->type_series_snapshot, $damage->thickness_snapshot])->filter(fn ($value) => $value !== '')->implode(' · ') ?: 'Standard' }} · {{ $damage->unit_snapshot }}</span></td>
                            <td class="px-3 py-3 whitespace-nowrap"><a href="{{ route('purchase-orders.show', $order->id) }}" class="font-semibold text-amber-700">PO #{{ $order->id }}</a></td>
                            <td class="px-3 py-3">{{ $order->supplier_name }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $receipt->restockNumber() }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $receipt->recordedBy->name }}<span class="block text-slate-600"><time datetime="{{ $receipt->created_at?->toIso8601String() }}">{{ $receipt->created_at?->format('M j, Y g:i A') ?? '—' }}</time></span></td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $damage->damaged_quantity }} {{ $damage->unit_snapshot }}</td>
                            <td class="px-3 py-3 whitespace-pre-wrap">{{ $damage->damage_note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-10 text-center text-slate-600">{{ $hasEvidence ? 'No damaged items match these filters.' : 'No damaged receiving records yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</main>
@endsection

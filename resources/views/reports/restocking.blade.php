@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
    <h1 class="mt-1 text-3xl font-bold">Restocking Report</h1>
    <p class="mt-2 text-slate-600">Each row records one accepted stock-in item. Newest receipts appear first.</p>
    <a href="{{ route('reports.index') }}" class="mt-3 inline-block font-semibold text-amber-700 print:hidden">Back to Reports</a>

    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50"><tr><th scope="col" class="px-3 py-3 text-left">Item</th><th scope="col" class="px-3 py-3 text-left">Receipt</th><th scope="col" class="px-3 py-3 text-left">Source</th><th scope="col" class="px-3 py-3 text-left">PO</th><th scope="col" class="px-3 py-3 text-left">Supplier</th><th scope="col" class="px-3 py-3 text-left">Recorded by / time</th><th scope="col" class="px-3 py-3 text-right">Accepted quantity</th><th scope="col" class="px-3 py-3 text-right">Actual unit cost</th><th scope="col" class="px-3 py-3 text-right">Line total</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    @php($receipt = $item->restock)
                    @php($order = $receipt->purchaseOrder)
                    <tr data-report-restock-item="{{ $item->id }}">
                        <td class="px-3 py-3"><span class="font-semibold">{{ $item->product_name_snapshot }}</span><span class="block text-slate-600">{{ collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])->filter(fn ($value) => $value !== '')->implode(' · ') ?: 'Standard' }} · {{ $item->unit_snapshot }}</span></td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ $receipt->restockNumber() }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ $receipt->purchase_order_id === null ? 'Stock In' : 'Purchase Order' }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">@if ($order)<a href="{{ route('purchase-orders.show', $order->id) }}" class="font-semibold text-amber-700">PO #{{ $order->id }}</a>@else — @endif</td>
                        <td class="px-3 py-3">{{ $order?->supplier_name ?? '—' }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ $receipt->recordedBy->name }}<span class="block text-slate-600"><time datetime="{{ $receipt->created_at?->toIso8601String() }}">{{ $receipt->created_at?->format('M j, Y g:i A') ?? '—' }}</time></span></td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">{{ $item->quantity }} {{ $item->unit_snapshot }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">₱{{ $item->unit_cost }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">₱{{ $item->line_total }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-10 text-center text-slate-600">No accepted stock-in history yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</main>
@endsection

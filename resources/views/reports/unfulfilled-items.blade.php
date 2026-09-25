@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
    <h1 class="mt-1 text-3xl font-bold">Unfulfilled Items Report</h1>
    <p class="mt-2 text-slate-600">Each row is one Purchase Order item with current outstanding demand. Older orders appear first.</p>
    <a href="{{ route('reports.index') }}" class="mt-3 inline-block font-semibold text-amber-700 print:hidden">Back to Reports</a>

    <form method="GET" action="{{ route('reports.unfulfilled-items') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_11rem_11rem_auto] print:hidden">
        <label><span class="text-sm font-medium">Supplier</span><input name="supplier" value="{{ $supplier }}" maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Search supplier">@if (isset($filterErrors['supplier']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['supplier'] }}</span>@endif</label>
        <label><span class="text-sm font-medium">Item</span><input name="item" value="{{ $itemSearch }}" maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Search item snapshots">@if (isset($filterErrors['item']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['item'] }}</span>@endif</label>
        <label><span class="text-sm font-medium">Open status</span><select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All open statuses</option><option value="pending" @selected($status === 'pending')>Pending</option><option value="partially_received" @selected($status === 'partially_received')>Partially received</option></select>@if (isset($filterErrors['status']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['status'] }}</span>@endif</label>
        <label><span class="text-sm font-medium">PO #</span><input name="po" value="{{ $po }}" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Exact ID">@if (isset($filterErrors['po']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['po'] }}</span>@endif</label>
        <div class="flex items-end gap-2"><button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-500">Apply filters</button><a href="{{ route('reports.unfulfilled-items') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Reset</a></div>
    </form>

    @if ($filterErrors !== [])
        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">The report was not run because one or more filters are invalid.</p>
    @else
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-label="Unfulfilled item summary"><h2 class="text-sm font-semibold text-slate-500">Unfulfilled PO lines</h2><p class="mt-2 text-3xl font-bold" data-report-count>{{ $lines->count() }}</p></section>
        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th scope="col" class="px-3 py-3 text-left">Item</th><th scope="col" class="px-3 py-3 text-left">PO / lineage</th><th scope="col" class="px-3 py-3 text-left">Supplier</th><th scope="col" class="px-3 py-3 text-left">Status</th><th scope="col" class="px-3 py-3 text-left">Created</th><th scope="col" class="px-3 py-3 text-right">Ordered</th><th scope="col" class="px-3 py-3 text-right">Accepted</th><th scope="col" class="px-3 py-3 text-right">Transferred</th><th scope="col" class="px-3 py-3 text-right">Outstanding</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lines as $line)
                        @php($item = $line['item'])
                        @php($order = $item->purchaseOrder)
                        <tr data-report-line="{{ $item->id }}">
                            <td class="px-3 py-3"><span class="font-semibold">{{ $item->product_name_snapshot }}</span><span class="block text-slate-600">{{ collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])->filter()->implode(' · ') }} · {{ $item->unit_snapshot }}</span></td>
                            <td class="px-3 py-3 whitespace-nowrap"><a href="{{ route('purchase-orders.show', $order->id) }}" class="font-semibold text-amber-700">PO #{{ $order->id }}</a>@if ($order->parent_purchase_order_id !== null)<span class="block">Parent: <a href="{{ route('purchase-orders.show', $order->parent_purchase_order_id) }}" class="text-amber-700">PO #{{ $order->parent_purchase_order_id }}</a></span>@endif @if ($order->children->isNotEmpty())<span class="block">Follow-up children: @foreach ($order->children as $child)<a href="{{ route('purchase-orders.show', $child->id) }}" class="mr-2 text-amber-700">PO #{{ $child->id }}</a>@endforeach</span>@endif</td>
                            <td class="px-3 py-3">{{ $order->supplier_name }}</td>
                            <td class="px-3 py-3 capitalize">{{ str_replace('_', ' ', $order->status) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap"><time datetime="{{ $order->created_at?->toIso8601String() }}">{{ $order->created_at?->format('M j, Y g:i A') ?? '—' }}</time></td>
                            <td class="px-3 py-3 text-right">{{ $line['ordered'] }}</td>
                            <td class="px-3 py-3 text-right">{{ $line['accepted'] }}</td>
                            <td class="px-3 py-3 text-right">{{ $line['transferred'] }}</td>
                            <td class="px-3 py-3 text-right font-bold">{{ $line['outstanding'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-10 text-center text-slate-600">No unfulfilled Purchase Order items match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</main>
@endsection

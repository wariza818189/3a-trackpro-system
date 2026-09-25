@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
    <h1 class="mt-1 text-3xl font-bold">Pending Purchase Orders Report</h1>
    <p class="mt-2 text-slate-600">Current outstanding demand on pending and partially received Purchase Orders.</p>
    <a href="{{ route('reports.index') }}" class="mt-3 inline-block font-semibold text-amber-700 print:hidden">Back to Reports</a>

    <form method="GET" action="{{ route('reports.pending-purchase-orders') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_15rem_auto] print:hidden">
        <label><span class="text-sm font-medium">Supplier</span><input name="supplier" value="{{ $supplier }}" maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Search supplier">@if (isset($filterErrors['supplier']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['supplier'] }}</span>@endif</label>
        <label><span class="text-sm font-medium">Open status</span><select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All open statuses</option><option value="pending" @selected($status === 'pending')>Pending</option><option value="partially_received" @selected($status === 'partially_received')>Partially received</option></select>@if (isset($filterErrors['status']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['status'] }}</span>@endif</label>
        <div class="flex items-end gap-2"><button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-500">Apply filters</button><a href="{{ route('reports.pending-purchase-orders') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Reset</a></div>
    </form>

    @if ($filterErrors !== [])
        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">The report was not run because one or more filters are invalid.</p>
    @else
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-label="Pending Purchase Orders summary"><h2 class="text-sm font-semibold text-slate-500">Matching Purchase Orders</h2><p class="mt-2 text-3xl font-bold" data-report-count>{{ $orders->count() }}</p></section>
        @forelse ($orders as $row)
            @php($order = $row['order'])
            <article class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" data-report-po="{{ $order->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-xl font-bold">PO #{{ $order->id }}</h2><p class="mt-1">{{ $order->supplier_name }}</p></div>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold capitalize text-amber-900">{{ str_replace('_', ' ', $order->status) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">Created: {{ $order->created_at?->format('M j, Y g:i A') ?? '—' }}</p>
                @if ($order->parent_purchase_order_id !== null)<p class="mt-1 text-sm">Parent: <a href="{{ route('purchase-orders.show', $order->parent_purchase_order_id) }}" class="font-semibold text-amber-700">PO #{{ $order->parent_purchase_order_id }}</a></p>@endif
                @if ($order->children->isNotEmpty())<p class="mt-1 text-sm">Follow-up children: @foreach ($order->children as $child)<a href="{{ route('purchase-orders.show', $child->id) }}" class="mr-3 font-semibold text-amber-700">PO #{{ $child->id }}</a>@endforeach</p>@endif
                <div class="mt-4 overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Open line</th><th class="px-3 py-2 text-right">Ordered</th><th class="px-3 py-2 text-right">Accepted</th><th class="px-3 py-2 text-right">Transferred</th><th class="px-3 py-2 text-right">Outstanding</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @foreach ($row['lines'] as $line)
                        @php($item = $line['item'])
                        <tr data-report-line="{{ $item->id }}"><td class="px-3 py-3"><span class="font-semibold">{{ $item->product_name_snapshot }}</span><span class="block text-slate-600">{{ collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])->filter()->implode(' · ') }} · {{ $item->unit_snapshot }}</span></td><td class="px-3 py-3 text-right">{{ $item->ordered_quantity }}</td><td class="px-3 py-3 text-right">{{ $line['accepted'] }}</td><td class="px-3 py-3 text-right">{{ $line['transferred'] }}</td><td class="px-3 py-3 text-right font-bold">{{ $line['outstanding'] }}</td></tr>
                    @endforeach
                </tbody></table></div>
                <a href="{{ route('purchase-orders.show', $order->id) }}" class="mt-4 inline-block font-semibold text-amber-700 print:hidden">View Purchase Order</a>
            </article>
        @empty
            <p class="mt-6 rounded-xl border border-slate-200 bg-white p-8 text-center text-slate-600">No open Purchase Orders with outstanding demand match these filters.</p>
        @endforelse
    @endif
</main>
@endsection

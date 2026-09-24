@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
    <a href="{{ route('purchase-orders.index') }}" class="text-sm font-semibold text-amber-700">← Back to Purchase Orders</a>

    @if ($errors->any())
        <section class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-900" role="alert">
            <h2 class="font-bold">The Purchase Order action could not be completed</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </section>
    @endif

    @if (session('purchase_order_confirmation'))
        @php
            $confirmation = session('purchase_order_confirmation');
        @endphp
        <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950" role="status" data-po-confirmation>
            <h2 class="font-bold">{{ $confirmation['replayed'] ? 'Purchase Order already recorded' : 'Purchase Order created' }}</h2>
            <p class="mt-1 text-sm">
                PO #{{ $confirmation['id'] }} · {{ $confirmation['supplier_name'] }} · {{ ucfirst(str_replace('_', ' ', $confirmation['status'])) }} ·
                {{ $confirmation['line_count'] }} {{ Str::plural('line', $confirmation['line_count']) }}
            </p>
            @if ($confirmation['replayed'])
                <p class="mt-2 text-sm">The equivalent earlier submission was reused; no duplicate Purchase Order was created.</p>
            @endif
        </section>
    @endif

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Procurement</p>
            <h1 class="mt-2 text-3xl font-bold">Purchase Order #{{ $purchaseOrder->id }}</h1>
            <p class="mt-2 text-slate-600">Historical supplier and item snapshots saved with this order.</p>
        </div>
        <div class="flex items-center gap-3">
            @if ($canEdit)
                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="inline-flex min-h-11 items-center rounded-lg bg-amber-600 px-4 py-2 font-bold text-white hover:bg-amber-700" data-po-edit>Edit Purchase Order</a>
            @endif
            @if ($canFollowUp)
                <a href="{{ route('purchase-orders.follow-up.create', $purchaseOrder) }}" class="inline-flex min-h-11 items-center rounded-lg bg-amber-600 px-4 py-2 font-bold text-white hover:bg-amber-700" data-po-follow-up>Create follow-up</a>
            @endif
            @if ($canReceive)
                <a href="{{ route('purchase-orders.receive.create', $purchaseOrder) }}" class="inline-flex min-h-11 items-center rounded-lg bg-amber-600 px-4 py-2 font-bold text-white hover:bg-amber-700" data-po-receive>Receive items</a>
            @endif
            <span class="w-fit rounded-full bg-slate-200 px-4 py-2 text-sm font-bold capitalize text-slate-800">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span>
        </div>
    </div>

    <dl class="mt-6 grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-3 sm:p-6">
        <div><dt class="text-sm text-slate-500">Supplier</dt><dd class="mt-1 font-semibold">{{ $purchaseOrder->supplier_name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Created by</dt><dd class="mt-1 font-semibold">{{ $purchaseOrder->createdBy->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Status</dt><dd class="mt-1 font-semibold capitalize">{{ str_replace('_', ' ', $purchaseOrder->status) }}</dd></div>
        <div><dt class="text-sm text-slate-500">Created</dt><dd class="mt-1 font-semibold">{{ $purchaseOrder->created_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
        <div><dt class="text-sm text-slate-500">Last updated</dt><dd class="mt-1 font-semibold">{{ $purchaseOrder->updated_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
        @if ($purchaseOrder->parent !== null)
            <div><dt class="text-sm text-slate-500">Parent Purchase Order</dt><dd class="mt-1 font-semibold"><a href="{{ route('purchase-orders.show', $purchaseOrder->parent) }}" class="text-amber-700 hover:text-amber-800">PO #{{ $purchaseOrder->parent->id }}</a></dd></div>
        @endif
        @if ($purchaseOrder->children->isNotEmpty())
            <div class="sm:col-span-2 lg:col-span-3"><dt class="text-sm text-slate-500">Follow-up Purchase Orders</dt><dd class="mt-1 flex flex-wrap gap-3 font-semibold">@foreach ($purchaseOrder->children as $child)<a href="{{ route('purchase-orders.show', $child) }}" class="text-amber-700 hover:text-amber-800" data-po-child="{{ $child->id }}">PO #{{ $child->id }} · {{ str_replace('_', ' ', $child->status) }}</a>@endforeach</dd></div>
        @endif
        @if ($purchaseOrder->notes !== null)
            <div class="sm:col-span-2 lg:col-span-3"><dt class="text-sm text-slate-500">Notes</dt><dd class="mt-1 whitespace-pre-line font-medium">{{ $purchaseOrder->notes }}</dd></div>
        @endif
    </dl>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="purchase-order-lines-heading">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 id="purchase-order-lines-heading" class="text-xl font-bold">Ordered items</h2>
            <p class="mt-1 text-sm text-slate-500">Names and descriptors are the snapshots captured for this Purchase Order.</p>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Product snapshot</th>
                        <th class="px-5 py-3">Variant snapshot</th>
                        <th class="px-5 py-3">Ordered quantity</th>
                        <th class="px-5 py-3">Accepted quantity</th>
                        <th class="px-5 py-3">Transferred quantity</th>
                        <th class="px-5 py-3">Outstanding quantity</th>
                        <th class="px-5 py-3">Transfer lineage</th>
                        @if ($admin)<th class="px-5 py-3">Expected unit cost</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($purchaseOrder->items as $item)
                        @php
                            $identity = collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])
                                ->filter(fn ($value) => $value !== '')
                                ->join(' · ') ?: 'Standard';
                        @endphp
                        <tr data-po-item="{{ $item->product_variant_id }}">
                            <td class="px-5 py-4 font-semibold">{{ $item->product_name_snapshot }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $identity }} · {{ $item->unit_snapshot }}</td>
                            <td class="px-5 py-4 font-medium">{{ $item->ordered_quantity }} {{ $item->unit_snapshot }}</td>
                            <td class="px-5 py-4 font-medium">{{ $lines[$item->id]['accepted'] }} {{ $item->unit_snapshot }}</td>
                            <td class="px-5 py-4 font-medium">{{ $lines[$item->id]['transferred'] }} {{ $item->unit_snapshot }}</td>
                            <td class="px-5 py-4 font-medium">{{ $lines[$item->id]['outstanding'] }} {{ $item->unit_snapshot }}</td>
                            <td class="px-5 py-4 text-slate-600">
                                @if ($item->outgoingTransfer?->targetItem?->purchaseOrder)
                                    To <a href="{{ route('purchase-orders.show', $item->outgoingTransfer->targetItem->purchaseOrder) }}" class="font-semibold text-amber-700">PO #{{ $item->outgoingTransfer->targetItem->purchase_order_id }}</a>
                                @elseif ($item->incomingTransfer?->sourceItem?->purchaseOrder)
                                    From <a href="{{ route('purchase-orders.show', $item->incomingTransfer->sourceItem->purchaseOrder) }}" class="font-semibold text-amber-700">PO #{{ $item->incomingTransfer->sourceItem->purchase_order_id }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            @if ($admin)<td class="px-5 py-4 font-medium">₱{{ $item->expected_unit_cost }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @foreach ($purchaseOrder->items as $item)
                @php
                    $identity = collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])
                        ->filter(fn ($value) => $value !== '')
                        ->join(' · ') ?: 'Standard';
                @endphp
                <article class="p-5" data-po-item-card="{{ $item->product_variant_id }}">
                    <h3 class="font-bold">{{ $item->product_name_snapshot }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $identity }} · {{ $item->unit_snapshot }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-slate-500">Ordered quantity</dt><dd class="font-semibold">{{ $item->ordered_quantity }} {{ $item->unit_snapshot }}</dd></div>
                        <div><dt class="text-slate-500">Accepted quantity</dt><dd class="font-semibold">{{ $lines[$item->id]['accepted'] }} {{ $item->unit_snapshot }}</dd></div>
                        <div><dt class="text-slate-500">Transferred quantity</dt><dd class="font-semibold">{{ $lines[$item->id]['transferred'] }} {{ $item->unit_snapshot }}</dd></div>
                        <div><dt class="text-slate-500">Outstanding quantity</dt><dd class="font-semibold">{{ $lines[$item->id]['outstanding'] }} {{ $item->unit_snapshot }}</dd></div>
                        @if ($item->outgoingTransfer?->targetItem?->purchaseOrder)
                            <div><dt class="text-slate-500">Transfer lineage</dt><dd class="font-semibold">To <a href="{{ route('purchase-orders.show', $item->outgoingTransfer->targetItem->purchaseOrder) }}" class="text-amber-700">PO #{{ $item->outgoingTransfer->targetItem->purchase_order_id }}</a></dd></div>
                        @elseif ($item->incomingTransfer?->sourceItem?->purchaseOrder)
                            <div><dt class="text-slate-500">Transfer lineage</dt><dd class="font-semibold">From <a href="{{ route('purchase-orders.show', $item->incomingTransfer->sourceItem->purchaseOrder) }}" class="text-amber-700">PO #{{ $item->incomingTransfer->sourceItem->purchase_order_id }}</a></dd></div>
                        @endif
                        @if ($admin)<div><dt class="text-slate-500">Expected unit cost</dt><dd class="font-semibold">₱{{ $item->expected_unit_cost }}</dd></div>@endif
                    </dl>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="receipt-history-heading">
        <h2 id="receipt-history-heading" class="text-xl font-bold">Receipt history</h2>
        @forelse ($receipts as $receipt)
            <article class="mt-5 border-t border-slate-200 pt-4" data-po-receipt="{{ $receipt->id }}">
                <h3 class="font-semibold">{{ $receipt->restockNumber() }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ $receipt->created_at?->format('M j, Y g:i A') ?? '—' }} · {{ $receipt->recordedBy->name }}@if ($receipt->reference_text) · Reference: {{ $receipt->reference_text }}@endif</p>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($receipt->items as $receivedItem)
                        <li>{{ $receivedItem->product_name_snapshot }} · {{ $receivedItem->quantity }} {{ $receivedItem->unit_snapshot }}@if ($admin) · Actual unit cost: ₱{{ $receivedItem->unit_cost }} · Line total: ₱{{ $receivedItem->line_total }}@endif</li>
                    @endforeach
                </ul>
                @if ($admin)<p class="mt-2 text-sm font-semibold">Receipt total: ₱{{ $receipt->total_cost }}</p>@endif
            </article>
        @empty
            <p class="mt-3 text-sm text-slate-500">No linked receipts yet.</p>
        @endforelse
    </section>
</main>
@endsection

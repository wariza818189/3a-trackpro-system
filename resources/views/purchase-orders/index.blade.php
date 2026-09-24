@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Procurement</p>
            <h1 class="mt-2 text-3xl font-bold">Purchase Orders</h1>
            <p class="mt-2 text-slate-600">Review saved supplier plans and their historical line snapshots.</p>
        </div>
        @if ($admin)<a href="{{ route('purchase-orders.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-600 px-4 py-2 font-bold text-white hover:bg-amber-700" data-po-create-action>
            Create Purchase Order
        </a>@endif
    </div>

    <form method="GET" action="{{ route('purchase-orders.index') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.35fr)_auto] sm:items-end">
        <label>
            <span class="text-sm font-semibold">Supplier</span>
            <input name="supplier" value="{{ $supplier }}" maxlength="150" placeholder="Search supplier" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
        </label>
        <label>
            <span class="text-sm font-semibold">Status</span>
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                <option value="">All statuses</option>
                @foreach ([
                    \App\Models\PurchaseOrder::STATUS_PENDING,
                    \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    \App\Models\PurchaseOrder::STATUS_COMPLETED,
                    \App\Models\PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER,
                ] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <button class="min-h-11 rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700">Filter</button>
            @if ($supplier !== '' || $status !== null)
                <a href="{{ route('purchase-orders.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="purchase-orders-heading">
        <h2 id="purchase-orders-heading" class="sr-only">Saved Purchase Orders</h2>
        @if ($purchaseOrders->isEmpty())
            <div class="px-6 py-14 text-center" data-po-empty-state>
                <p class="font-semibold text-slate-700">{{ $supplier !== '' || $status !== null ? 'No matching Purchase Orders were found.' : 'No Purchase Orders have been created yet.' }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $supplier !== '' || $status !== null ? 'Adjust or clear the filters to review other orders.' : 'Create the first pending Purchase Order when purchasing is ready.' }}</p>
            </div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Purchase Order</th>
                            <th class="px-5 py-3">Supplier</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Creator</th>
                            <th class="px-5 py-3">Created</th>
                            <th class="px-5 py-3">Lines</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($purchaseOrders as $purchaseOrder)
                            <tr data-po-index-row="{{ $purchaseOrder->id }}">
                                <td class="px-5 py-4 font-bold">PO #{{ $purchaseOrder->id }}</td>
                                <td class="px-5 py-4">{{ $purchaseOrder->supplier_name }}</td>
                                <td class="px-5 py-4 capitalize">{{ str_replace('_', ' ', $purchaseOrder->status) }}</td>
                                <td class="px-5 py-4">{{ $purchaseOrder->createdBy->name }}</td>
                                <td class="px-5 py-4 whitespace-nowrap">{{ $purchaseOrder->created_at?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="px-5 py-4"><span data-po-line-count>{{ $purchaseOrder->items_count }}</span></td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="font-semibold text-amber-700 hover:text-amber-800" data-po-view>View</a>
                                        @if ($admin && $purchaseOrder->isEditable() && ! $purchaseOrder->has_transfer_activity)
                                            <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="font-semibold text-slate-700 hover:text-slate-900" data-po-edit>Edit</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($purchaseOrders as $purchaseOrder)
                    <article class="p-5" data-po-index-card="{{ $purchaseOrder->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold">PO #{{ $purchaseOrder->id }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $purchaseOrder->supplier_name }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-500">Creator</dt><dd class="font-medium">{{ $purchaseOrder->createdBy->name }}</dd></div>
                            <div><dt class="text-slate-500">Lines</dt><dd class="font-medium" data-po-line-count>{{ $purchaseOrder->items_count }}</dd></div>
                            <div class="col-span-2"><dt class="text-slate-500">Created</dt><dd class="font-medium">{{ $purchaseOrder->created_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
                        </dl>
                        <div class="mt-4 flex gap-4">
                            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="inline-flex min-h-11 items-center font-semibold text-amber-700" data-po-view>View Purchase Order</a>
                            @if ($admin && $purchaseOrder->isEditable() && ! $purchaseOrder->has_transfer_activity)
                                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="inline-flex min-h-11 items-center font-semibold text-slate-700" data-po-edit>Edit</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-6">{{ $purchaseOrders->links() }}</div>
</main>
@endsection

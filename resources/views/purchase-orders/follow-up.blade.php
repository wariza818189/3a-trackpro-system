@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="text-sm font-semibold text-amber-700">← Back to Purchase Order #{{ $purchaseOrder->id }}</a>
    <div class="mt-5">
        <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Procurement</p>
        <h1 class="mt-2 text-3xl font-bold">Create follow-up Purchase Order</h1>
        <p class="mt-2 text-slate-600">Select source lines to move their entire current outstanding quantity to a new Purchase Order.</p>
    </div>

    @if ($errors->any())
        <section class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-900" role="alert">
            <h2 class="font-bold">Review the follow-up details</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </section>
    @endif

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid gap-4 sm:grid-cols-3">
            <div><dt class="text-sm text-slate-500">Source Purchase Order</dt><dd class="mt-1 font-semibold">PO #{{ $purchaseOrder->id }}</dd></div>
            <div><dt class="text-sm text-slate-500">Source supplier</dt><dd class="mt-1 font-semibold">{{ $purchaseOrder->supplier_name }}</dd></div>
            <div><dt class="text-sm text-slate-500">Status</dt><dd class="mt-1 font-semibold capitalize">{{ str_replace('_', ' ', $purchaseOrder->status) }}</dd></div>
        </dl>
    </section>

    <form method="POST" action="{{ route('purchase-orders.follow-up.store', $purchaseOrder) }}" class="mt-6 space-y-6">
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}" data-follow-up-token>

        <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
            <label>
                <span class="text-sm font-semibold">Child supplier</span>
                <input name="supplier_name" value="{{ $supplierName }}" maxlength="150" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-follow-up-supplier>
                <span class="mt-1 block text-xs text-slate-500">Prefilled from the source; you may choose a different supplier.</span>
            </label>
            <label>
                <span class="text-sm font-semibold">Notes <span class="font-normal text-slate-500">(optional)</span></span>
                <textarea name="notes" maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ $notesValue }}</textarea>
            </label>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-xl font-bold">Eligible outstanding lines</h2>
                <p class="mt-1 text-sm text-slate-500">Each selected line transfers its complete outstanding quantity shown below.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($eligibleItems as $index => $item)
                    @php
                        $oldRows = old('items', []);
                        $oldRow = collect(is_array($oldRows) ? $oldRows : [])->first(
                            fn ($row) => is_array($row) && (string) ($row['source_purchase_order_item_id'] ?? '') === (string) $item->id
                        );
                        $selected = is_array($oldRow) && ($oldRow['selected'] ?? null) === '1';
                        $cost = is_array($oldRow) ? ($oldRow['expected_unit_cost'] ?? $item->expected_unit_cost) : $item->expected_unit_cost;
                        $identity = collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])
                            ->filter(fn ($value) => $value !== '')
                            ->join(' · ') ?: 'Standard';
                    @endphp
                    <article class="p-5" data-follow-up-line="{{ $item->id }}">
                        <input type="hidden" name="items[{{ $index }}][source_purchase_order_item_id]" value="{{ $item->id }}">
                        <div class="grid gap-5 lg:grid-cols-[auto_minmax(0,1fr)_minmax(12rem,0.3fr)] lg:items-start">
                            <label class="flex min-h-11 items-center gap-3 font-semibold">
                                <input type="checkbox" name="items[{{ $index }}][selected]" value="1" @checked($selected) class="h-5 w-5 rounded border-slate-300 text-amber-600">
                                Select
                            </label>
                            <div>
                                <h3 class="font-bold">{{ $item->product_name_snapshot }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $identity }} · {{ $item->unit_snapshot }}</p>
                                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                    <div><dt class="text-slate-500">Ordered</dt><dd class="font-semibold">{{ $item->ordered_quantity }}</dd></div>
                                    <div><dt class="text-slate-500">Accepted</dt><dd class="font-semibold">{{ $lines[$item->id]['accepted'] }}</dd></div>
                                    <div><dt class="text-slate-500">Transferred</dt><dd class="font-semibold">{{ $lines[$item->id]['transferred'] }}</dd></div>
                                    <div><dt class="text-slate-500">Outstanding</dt><dd class="font-semibold">{{ $lines[$item->id]['outstanding'] }} {{ $item->unit_snapshot }}</dd></div>
                                </dl>
                            </div>
                            <label>
                                <span class="text-sm font-semibold">Expected unit cost</span>
                                <input name="items[{{ $index }}][expected_unit_cost]" value="{{ $cost }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-follow-up-cost>
                            </label>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button class="min-h-11 rounded-lg bg-amber-600 px-5 py-2 font-bold text-white hover:bg-amber-700">Create follow-up Purchase Order</button>
            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-5 py-2 font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</main>
@endsection

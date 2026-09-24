@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="text-sm font-semibold text-amber-700">← Back to Purchase Order #{{ $purchaseOrder->id }}</a>
    <h1 class="mt-5 text-3xl font-bold">Receive Purchase Order #{{ $purchaseOrder->id }}</h1>
    <p class="mt-2 text-slate-600">Enter accepted quantities for this delivery. Leave other lines blank; their outstanding quantities remain open.</p>

    <form method="POST" action="{{ route('purchase-orders.receive.store', $purchaseOrder) }}" class="mt-6 space-y-6">
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}" data-receive-token>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="text-sm font-semibold">Delivery reference (optional)</span><input name="reference_text" value="{{ old('reference_text', '') }}" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="block"><span class="text-sm font-semibold">Notes (optional)</span><input name="notes" value="{{ old('notes', '') }}" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            </div>
        </section>
        <section class="space-y-4" aria-label="Outstanding Purchase Order lines">
            @foreach ($outstandingItems as $index => $item)
                @php
                    $identity = collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard';
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" data-receive-line="{{ $item->id }}">
                    <h2 class="font-bold">{{ $item->product_name_snapshot }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $identity }} · {{ $item->unit_snapshot }}</p>
                    <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
                        <div><dt class="text-slate-500">Ordered</dt><dd class="font-semibold">{{ $item->ordered_quantity }} {{ $item->unit_snapshot }}</dd></div>
                        <div><dt class="text-slate-500">Accepted</dt><dd class="font-semibold">{{ $lines[$item->id]['accepted'] }} {{ $item->unit_snapshot }}</dd></div>
                        <div><dt class="text-slate-500">Outstanding</dt><dd class="font-semibold">{{ $lines[$item->id]['outstanding'] }} {{ $item->unit_snapshot }}</dd></div>
                    </dl>
                    <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block"><span class="text-sm font-semibold">Accepted quantity ({{ $item->unit_snapshot }})</span><input name="items[{{ $index }}][accepted_quantity]" value="{{ old('items.'.$index.'.accepted_quantity', '') }}" inputmode="decimal" placeholder="0.000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="block"><span class="text-sm font-semibold">Actual unit cost</span><input name="items[{{ $index }}][actual_unit_cost]" value="{{ old('items.'.$index.'.actual_unit_cost', '') }}" inputmode="decimal" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    </div>
                </article>
            @endforeach
        </section>
        <button class="min-h-11 rounded-lg bg-amber-600 px-5 py-2 font-bold text-white hover:bg-amber-700">Record accepted delivery</button>
    </form>
</main>
@endsection

@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="text-sm font-semibold text-amber-700">← Back to Purchase Order #{{ $purchaseOrder->id }}</a>
    <h1 class="mt-5 text-3xl font-bold">Receive Purchase Order #{{ $purchaseOrder->id }}</h1>
    <p class="mt-2 text-slate-600">Enter accepted or damaged quantities for this delivery. Leave other lines blank. Damaged items remain outstanding until accepted or transferred.</p>

    @if ($errors->any())
        <section class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-900" role="alert">
            <h2 class="font-bold">The delivery could not be recorded</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </section>
    @endif

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
                    $acceptedInput = old('items.'.$index.'.accepted_quantity', '');
                    $costInput = old('items.'.$index.'.actual_unit_cost', '');
                    $damageInput = old('items.'.$index.'.damaged_quantity', '');
                    $damageNoteInput = old('items.'.$index.'.damage_note', '');
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
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <h3 class="font-semibold text-emerald-950">Accepted items</h3>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="block"><span class="text-sm font-semibold">Accepted quantity ({{ $item->unit_snapshot }})</span><input name="items[{{ $index }}][accepted_quantity]" value="{{ is_string($acceptedInput) ? $acceptedInput : '' }}" inputmode="decimal" placeholder="0.000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                                <label class="block"><span class="text-sm font-semibold">Actual unit cost</span><input name="items[{{ $index }}][actual_unit_cost]" value="{{ is_string($costInput) ? $costInput : '' }}" inputmode="decimal" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                            </div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <h3 class="font-semibold text-amber-950">Damaged items</h3>
                            <p class="mt-1 text-xs text-amber-900">A damage note is required when you enter a damaged quantity. Damage does not reduce outstanding demand.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="block"><span class="text-sm font-semibold">Damaged quantity ({{ $item->unit_snapshot }})</span><input name="items[{{ $index }}][damaged_quantity]" value="{{ is_string($damageInput) ? $damageInput : '' }}" inputmode="decimal" placeholder="0.000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                                <label class="block"><span class="text-sm font-semibold">Damage note</span><textarea name="items[{{ $index }}][damage_note]" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ is_string($damageNoteInput) ? $damageNoteInput : '' }}</textarea></label>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
        <button class="min-h-11 rounded-lg bg-amber-600 px-5 py-2 font-bold text-white hover:bg-amber-700">Record delivery</button>
    </form>
</main>
@endsection

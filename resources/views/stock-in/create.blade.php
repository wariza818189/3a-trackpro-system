@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <a href="{{ route('stock-in.index') }}" class="text-sm font-medium text-amber-700">← Back to Stock In history</a>
    <h1 class="mt-4 text-3xl font-bold">Record Stock In</h1>
    <p class="mt-2 text-slate-600">Record one receipt containing up to 100 initialized, active variants.</p>

    @if ($variants->isEmpty())
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
            No active initialized variants are available. Complete Opening Inventory first.
        </div>
    @else
        @php($rows = old('items', [['product_variant_id' => '', 'quantity' => '', 'unit_cost' => '']]))
        <form method="POST" action="{{ route('stock-in.store') }}" class="mt-6 space-y-6" data-stock-in-form data-max-items="100">
            @csrf
            <input type="hidden" name="submission_token" value="{{ $submissionToken }}">

            <section class="grid gap-5 rounded-xl border bg-white p-6 shadow-sm md:grid-cols-2">
                <label>
                    <span class="text-sm font-semibold">Reference (optional)</span>
                    <input name="reference_text" value="{{ old('reference_text') }}" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Delivery receipt or other reference">
                </label>
                <label>
                    <span class="text-sm font-semibold">Notes (optional)</span>
                    <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('notes') }}</textarea>
                </label>
            </section>

            <section class="rounded-xl border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold">Received items</h2>
                        <p class="text-sm text-slate-500">Each variant may appear once. Unit cost may be zero for free stock.</p>
                    </div>
                    <button type="button" data-add-stock-in-item class="rounded-lg border border-amber-400 px-3 py-2 text-sm font-semibold text-amber-800">Add item</button>
                </div>

                <div class="mt-5 space-y-4" data-stock-in-items>
                    @foreach ($rows as $index => $row)
                        <div class="rounded-lg border border-slate-200 p-4" data-stock-in-item>
                            <div class="grid gap-4 lg:grid-cols-12">
                                <label class="lg:col-span-6">
                                    <span class="text-sm font-semibold">Variant</span>
                                    <select name="items[{{ $index }}][product_variant_id]" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-stock-in-variant>
                                        <option value="">Select a variant</option>
                                        @foreach ($variants as $variant)
                                            @php($identity = collect([$variant->size, $variant->type_series, $variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                                            <option value="{{ $variant->id }}"
                                                data-product="{{ $variant->product->name }}"
                                                data-identity="{{ $identity }}"
                                                data-unit="{{ $variant->unit }}"
                                                data-mode="{{ $variant->quantity_mode }}"
                                                data-stock="{{ $variant->current_stock }}"
                                                data-stock-display="{{ $variant->displayCurrentStock() }}"
                                                @selected((string) ($row['product_variant_id'] ?? '') === (string) $variant->id)>
                                                {{ $variant->product->name }} — {{ $identity }} — {{ $variant->unit }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="lg:col-span-3">
                                    <span class="text-sm font-semibold">Received quantity</span>
                                    <input name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                </label>
                                <label class="lg:col-span-3">
                                    <span class="text-sm font-semibold">Unit purchase cost</span>
                                    <input name="items[{{ $index }}][unit_cost]" value="{{ $row['unit_cost'] ?? '' }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                </label>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                                <p class="text-slate-600" data-stock-in-metadata>Select a variant to see its unit, quantity mode, and current stock.</p>
                                <button type="button" data-remove-stock-in-item class="font-medium text-red-700">Remove item</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template data-stock-in-template>
                    <div class="rounded-lg border border-slate-200 p-4" data-stock-in-item>
                        <div class="grid gap-4 lg:grid-cols-12">
                            <label class="lg:col-span-6"><span class="text-sm font-semibold">Variant</span><select name="items[__INDEX__][product_variant_id]" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-stock-in-variant><option value="">Select a variant</option>@foreach ($variants as $variant)@php($identity = collect([$variant->size, $variant->type_series, $variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')<option value="{{ $variant->id }}" data-product="{{ $variant->product->name }}" data-identity="{{ $identity }}" data-unit="{{ $variant->unit }}" data-mode="{{ $variant->quantity_mode }}" data-stock="{{ $variant->current_stock }}" data-stock-display="{{ $variant->displayCurrentStock() }}">{{ $variant->product->name }} — {{ $identity }} — {{ $variant->unit }}</option>@endforeach</select></label>
                            <label class="lg:col-span-3"><span class="text-sm font-semibold">Received quantity</span><input name="items[__INDEX__][quantity]" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                            <label class="lg:col-span-3"><span class="text-sm font-semibold">Unit purchase cost</span><input name="items[__INDEX__][unit_cost]" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm"><p class="text-slate-600" data-stock-in-metadata>Select a variant to see its unit, quantity mode, and current stock.</p><button type="button" data-remove-stock-in-item class="font-medium text-red-700">Remove item</button></div>
                    </div>
                </template>
            </section>

            <div class="flex justify-end gap-3">
                <a href="{{ route('stock-in.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Cancel</a>
                <button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Record Stock In</button>
            </div>
        </form>
    @endif
</main>
@endsection

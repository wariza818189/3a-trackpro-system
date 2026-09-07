@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-3xl px-6 py-10">
    <a href="{{ route('stock-corrections.index') }}" class="text-sm font-medium text-amber-700">← Back to Stock Correction</a>
    <h1 class="mt-4 text-3xl font-bold">Record Stock Correction</h1>
    <p class="mt-2 text-slate-600">Enter the verified physical stock. The system will calculate the signed adjustment.</p>

    <dl class="mt-6 grid gap-4 rounded-xl border bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-500">Category</dt><dd class="font-medium">{{ $productVariant->product->category->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Product</dt><dd class="font-medium">{{ $productVariant->product->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Variant</dt><dd class="font-medium">{{ collect([$productVariant->size, $productVariant->type_series, $productVariant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard' }}</dd></div>
        <div><dt class="text-sm text-slate-500">Unit</dt><dd class="font-medium">{{ $productVariant->unit }}</dd></div>
        <div><dt class="text-sm text-slate-500">Quantity mode</dt><dd class="font-medium capitalize">{{ $productVariant->quantity_mode }}</dd></div>
        <div><dt class="text-sm text-slate-500">Current stock</dt><dd class="font-medium">{{ $productVariant->current_stock }}</dd></div>
    </dl>

    <form method="POST" action="{{ route('stock-corrections.store', $productVariant) }}" class="mt-6 space-y-5 rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        <input type="hidden" name="expected_movement_id" value="{{ $latestMovementId }}">
        <label class="block">
            <span class="text-sm font-semibold">Corrected physical stock</span>
            <input name="corrected_stock" value="{{ old('corrected_stock') }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" aria-describedby="corrected-stock-help">
            <span id="corrected-stock-help" class="mt-1 block text-sm text-slate-500">Use a nonnegative {{ $productVariant->quantity_mode === 'whole' ? 'whole number' : 'quantity with up to three decimal places' }}.</span>
        </label>
        <label class="block">
            <span class="text-sm font-semibold">Reason</span>
            <textarea name="reason" rows="4" maxlength="1000" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Physical count discrepancy">{{ old('reason') }}</textarea>
        </label>
        <div class="flex justify-end gap-3">
            <a href="{{ route('stock-corrections.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Cancel</a>
            <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Record Stock Correction</button>
        </div>
    </form>
</main>
@endsection

@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-3xl px-6 py-10">
    <a href="{{ route('opening-inventory.index') }}" class="text-sm font-medium text-amber-700">← Back to Opening Inventory</a>
    <h1 class="mt-4 text-3xl font-bold">Record opening inventory</h1>
    <p class="mt-2 text-slate-600">Opening inventory is a one-time physical starting count. Zero is valid. Later stock changes must use their appropriate inventory workflow.</p>

    <dl class="mt-6 grid gap-4 rounded-xl border bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-500">Category</dt><dd class="font-medium">{{ $productVariant->product->category->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Product</dt><dd class="font-medium">{{ $productVariant->product->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Variant</dt><dd class="font-medium">{{ collect([$productVariant->size, $productVariant->type_series, $productVariant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard' }}</dd></div>
        <div><dt class="text-sm text-slate-500">Unit</dt><dd class="font-medium">{{ $productVariant->unit }}</dd></div>
        <div><dt class="text-sm text-slate-500">Quantity mode</dt><dd class="font-medium capitalize">{{ $productVariant->quantity_mode }}</dd></div>
        <div><dt class="text-sm text-slate-500">Current stock</dt><dd class="font-medium">{{ $productVariant->displayCurrentStock() }}</dd></div>
    </dl>

    <form method="POST" action="{{ route('opening-inventory.store', $productVariant) }}" class="mt-6 space-y-5 rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        <label class="block">
            <span class="text-sm font-semibold">Opening quantity</span>
            <input name="opening_quantity" value="{{ old('opening_quantity') }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" aria-describedby="quantity-help">
            <span id="quantity-help" class="mt-1 block text-sm text-slate-500">Use a nonnegative {{ $productVariant->quantity_mode === 'whole' ? 'whole number' : 'quantity with up to three decimal places' }}.</span>
        </label>
        <label class="block">
            <span class="text-sm font-semibold">Reason</span>
            <textarea name="reason" rows="4" maxlength="1000" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Initial physical count">{{ old('reason') }}</textarea>
        </label>
        <div class="flex justify-end gap-3">
            <a href="{{ route('opening-inventory.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Cancel</a>
            <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Record opening inventory</button>
        </div>
    </form>
</main>
@endsection

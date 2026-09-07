@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <h1 class="text-3xl font-bold">Stock Correction</h1>
    <p class="mt-1 text-slate-600">Reconcile initialized inventory to a verified physical count.</p>

    <form method="GET" action="{{ route('stock-corrections.index') }}" class="mt-6 grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-4">
        <label>
            <span class="text-sm font-medium">Search</span>
            <input name="search" value="{{ $search }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
        </label>
        <label>
            <span class="text-sm font-medium">Category</span>
            <select name="category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="text-sm font-medium">Product</span>
            <select name="product" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected($productId === $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Apply filters</button>
        </div>
    </form>

    <section class="mt-8" aria-labelledby="eligible-variants-heading">
        <h2 id="eligible-variants-heading" class="text-xl font-bold">Initialized active variants</h2>
        <div class="mt-3 overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-sm">Category</th>
                            <th class="px-3 py-3 text-left text-sm">Product</th>
                            <th class="px-3 py-3 text-left text-sm">Variant identity</th>
                            <th class="px-3 py-3 text-left text-sm">Unit</th>
                            <th class="px-3 py-3 text-left text-sm">Quantity mode</th>
                            <th class="px-3 py-3 text-right text-sm">Current stock</th>
                            <th class="px-3 py-3 text-right text-sm">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($variants as $variant)
                            @php($identity = collect([$variant->size, $variant->type_series, $variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                            <tr>
                                <td class="px-3 py-3 text-sm">{{ $variant->product->category->name }}</td>
                                <td class="px-3 py-3 text-sm font-medium">{{ $variant->product->name }}</td>
                                <td class="px-3 py-3 text-sm">{{ $identity }}</td>
                                <td class="px-3 py-3 text-sm">{{ $variant->unit }}</td>
                                <td class="px-3 py-3 text-sm capitalize">{{ $variant->quantity_mode }}</td>
                                <td class="px-3 py-3 text-right text-sm">{{ $variant->current_stock }}</td>
                                <td class="px-3 py-3 text-right text-sm">
                                    <a href="{{ route('stock-corrections.create', $variant) }}" class="rounded border border-amber-400 px-3 py-1.5 font-medium text-amber-800">Correct stock</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No initialized active variants found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $variants->links() }}</div>
    </section>

    <section class="mt-10" aria-labelledby="correction-history-heading">
        <h2 id="correction-history-heading" class="text-xl font-bold">Correction history</h2>
        <p class="mt-1 text-sm text-slate-600">Immutable movement history shown with current catalog names.</p>
        <div class="mt-3 overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-sm">Date and time</th>
                            <th class="px-3 py-3 text-left text-sm">Category / Product / Variant</th>
                            <th class="px-3 py-3 text-right text-sm">Before</th>
                            <th class="px-3 py-3 text-right text-sm">Change</th>
                            <th class="px-3 py-3 text-right text-sm">After</th>
                            <th class="px-3 py-3 text-left text-sm">Performed by</th>
                            <th class="px-3 py-3 text-left text-sm">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($history as $movement)
                            @php($identity = collect([$movement->variant->size, $movement->variant->type_series, $movement->variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                            <tr>
                                <td class="px-3 py-3 text-sm">{{ $movement->created_at?->format('M j, Y g:i A') }}</td>
                                <td class="px-3 py-3 text-sm"><span class="font-medium">{{ $movement->variant->product->category->name }} / {{ $movement->variant->product->name }}</span><br><span class="text-slate-500">{{ $identity }} · {{ $movement->variant->unit }}</span></td>
                                <td class="px-3 py-3 text-right text-sm">{{ $movement->quantity_before }}</td>
                                <td class="px-3 py-3 text-right text-sm">{{ str_starts_with($movement->quantity_change, '-') ? $movement->quantity_change : '+'.$movement->quantity_change }}</td>
                                <td class="px-3 py-3 text-right text-sm">{{ $movement->quantity_after }}</td>
                                <td class="px-3 py-3 text-sm">{{ $movement->performedBy->name }}</td>
                                <td class="max-w-sm px-3 py-3 text-sm">{{ $movement->reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No Stock Correction history yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $history->links() }}</div>
    </section>
</main>
@endsection

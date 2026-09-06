@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <h1 class="text-3xl font-bold">Opening Inventory</h1>
    <p class="mt-1 text-slate-600">Record each variant's one-time physical starting count.</p>

    <form method="GET" action="{{ route('opening-inventory.index') }}" class="mt-6 grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-5">
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
        <label>
            <span class="text-sm font-medium">Opening status</span>
            <select name="initialization" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                @foreach (['all' => 'All', 'initialized' => 'Initialized', 'not_initialized' => 'Not initialized'] as $value => $label)
                    <option value="{{ $value }}" @selected($initialization === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Apply filters</button>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-xl border bg-white shadow-sm">
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
                        <th class="px-3 py-3 text-left text-sm">Opening status</th>
                        <th class="px-3 py-3 text-right text-sm">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($variants as $variant)
                        @php
                            $activeHierarchy = $variant->status === \App\Models\ProductVariant::STATUS_ACTIVE
                                && $variant->product->status === \App\Models\Product::STATUS_ACTIVE
                                && $variant->product->category->status === \App\Models\Category::STATUS_ACTIVE;
                            $eligible = ! $variant->opening_inventory_recorded
                                && ! $variant->has_stock_movement
                                && ! $variant->has_sale_history
                                && ! $variant->has_restock_history
                                && (string) $variant->current_stock === '0.000'
                                && $activeHierarchy;
                        @endphp
                        <tr>
                            <td class="px-3 py-3 text-sm">{{ $variant->product->category->name }}</td>
                            <td class="px-3 py-3 text-sm font-medium">{{ $variant->product->name }}</td>
                            <td class="px-3 py-3 text-sm">{{ collect([$variant->size, $variant->type_series, $variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard' }}</td>
                            <td class="px-3 py-3 text-sm">{{ $variant->unit }}</td>
                            <td class="px-3 py-3 text-sm capitalize">{{ $variant->quantity_mode }}</td>
                            <td class="px-3 py-3 text-right text-sm">{{ $variant->current_stock }}</td>
                            <td class="px-3 py-3 text-sm">
                                @if ($variant->opening_inventory_recorded)
                                    <span class="rounded bg-emerald-100 px-2 py-1 text-emerald-800">Initialized</span>
                                @else
                                    <span class="rounded bg-slate-100 px-2 py-1 text-slate-700">Not initialized</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right text-sm">
                                @if ($eligible)
                                    <a href="{{ route('opening-inventory.create', $variant) }}" class="rounded border border-amber-400 px-3 py-1.5 font-medium text-amber-800">Record opening inventory</a>
                                @elseif (! $variant->opening_inventory_recorded)
                                    <span class="text-slate-500">Opening inventory unavailable</span>
                                @else
                                    <span class="text-slate-500">Completed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No variants found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $variants->links() }}</div>
</main>
@endsection

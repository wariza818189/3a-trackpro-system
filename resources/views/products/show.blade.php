@extends('layouts.app')
@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <a href="{{ route('products.index') }}" class="text-sm font-medium text-amber-700 hover:underline">Back to Products</a>
    <h1 class="mt-4 text-3xl font-bold">{{ $product->name }}</h1>

    <section class="mt-6 rounded-xl border bg-white p-6 shadow-sm" aria-labelledby="product-summary">
        <h2 id="product-summary" class="text-xl font-semibold">Product</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-500">Category</dt><dd class="font-medium">{{ $product->category->name }}</dd></div>
            <div><dt class="text-sm text-slate-500">Status</dt><dd class="font-medium capitalize">{{ $product->status }}</dd></div>
        </dl>
    </section>

    <section class="mt-8" aria-labelledby="product-variants">
        <h2 id="product-variants" class="text-xl font-semibold">Variants</h2>
        <div class="mt-4 overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr>
                        <th class="px-3 py-3 text-left">Size</th>
                        <th class="px-3 py-3 text-left">Type / series</th>
                        <th class="px-3 py-3 text-left">Thickness</th>
                        <th class="px-3 py-3 text-left">Unit</th>
                        <th class="px-3 py-3 text-left">Quantity mode</th>
                        <th class="px-3 py-3 text-right">Selling price</th>
                        <th class="px-3 py-3 text-right">Current stock</th>
                        <th class="px-3 py-3 text-right">Low-stock threshold</th>
                        <th class="px-3 py-3 text-left">Stock state</th>
                        <th class="px-3 py-3 text-left">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($product->variants as $variant)
                            @php
                                $stockState = (string) $variant->current_stock === '0.000'
                                    ? 'Out of stock'
                                    : (bccomp((string) $variant->current_stock, (string) $variant->low_stock_threshold, 3) <= 0 ? 'Low stock' : 'In stock');
                            @endphp
                            <tr>
                                <td class="px-3 py-3">{{ $variant->size ?: '—' }}</td>
                                <td class="px-3 py-3">{{ $variant->type_series ?: '—' }}</td>
                                <td class="px-3 py-3">{{ $variant->thickness ?: '—' }}</td>
                                <td class="px-3 py-3">{{ $variant->unit }}</td>
                                <td class="px-3 py-3 capitalize">{{ $variant->quantity_mode }}</td>
                                <td class="px-3 py-3 text-right">{{ $variant->selling_price }}</td>
                                <td class="px-3 py-3 text-right">{{ $variant->displayCurrentStock() }}</td>
                                <td class="px-3 py-3 text-right">{{ $variant->displayLowStockThreshold() }}</td>
                                <td class="px-3 py-3">{{ $stockState }}</td>
                                <td class="px-3 py-3 capitalize">{{ $variant->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-slate-500">No variants available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
@endsection

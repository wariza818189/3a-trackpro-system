@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
    <h1 class="mt-1 text-3xl font-bold">Inventory Report</h1>
    <p class="mt-2 text-slate-600">Current stock by Variant, including archived catalog records. Category, Product, and Variant statuses are shown separately from stock state.</p>
    <a href="{{ route('reports.index') }}" class="mt-3 inline-block font-semibold text-amber-700 print:hidden">Back to Reports</a>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr>
                    <th class="px-3 py-3 text-left">Category</th>
                    <th class="px-3 py-3 text-left">Category status</th>
                    <th class="px-3 py-3 text-left">Product</th>
                    <th class="px-3 py-3 text-left">Product status</th>
                    <th class="px-3 py-3 text-left">Size</th>
                    <th class="px-3 py-3 text-left">Type / series</th>
                    <th class="px-3 py-3 text-left">Thickness</th>
                    <th class="px-3 py-3 text-left">Unit</th>
                    <th class="px-3 py-3 text-left">Quantity mode</th>
                    <th class="px-3 py-3 text-left">Variant status</th>
                    <th class="px-3 py-3 text-right">Current stock</th>
                    <th class="px-3 py-3 text-right">Low-stock threshold</th>
                    <th class="px-3 py-3 text-left">Stock state</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($variants as $variant)
                        @php
                            $stockState = bccomp((string) $variant->current_stock, '0.000', 3) === 0
                                ? 'Out of stock'
                                : (bccomp((string) $variant->current_stock, (string) $variant->low_stock_threshold, 3) <= 0 ? 'Low stock' : 'In stock');
                        @endphp
                        <tr data-report-variant="{{ $variant->id }}">
                            <td class="px-3 py-3">{{ $variant->product->category->name }}</td>
                            <td class="px-3 py-3 font-semibold capitalize {{ $variant->product->category->status === 'archived' ? 'text-slate-600' : 'text-emerald-700' }}">{{ $variant->product->category->status }}</td>
                            <td class="px-3 py-3 font-semibold">{{ $variant->product->name }}</td>
                            <td class="px-3 py-3 font-semibold capitalize {{ $variant->product->status === 'archived' ? 'text-slate-600' : 'text-emerald-700' }}">{{ $variant->product->status }}</td>
                            <td class="px-3 py-3">{{ $variant->size ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $variant->type_series ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $variant->thickness ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $variant->unit }}</td>
                            <td class="px-3 py-3 capitalize">{{ $variant->quantity_mode }}</td>
                            <td class="px-3 py-3 font-semibold capitalize {{ $variant->status === 'archived' ? 'text-slate-600' : 'text-emerald-700' }}">{{ $variant->status }}</td>
                            <td class="px-3 py-3 text-right">{{ $variant->displayCurrentStock() }}</td>
                            <td class="px-3 py-3 text-right">{{ $variant->displayLowStockThreshold() }}</td>
                            <td class="px-3 py-3">{{ $stockState }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-4 py-10 text-center text-slate-600">No inventory records are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection

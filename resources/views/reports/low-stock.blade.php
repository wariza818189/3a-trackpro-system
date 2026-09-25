@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
    <h1 class="mt-1 text-3xl font-bold">Low Stock Report</h1>
    <p class="mt-2 text-slate-600">Active Variants at or below their configured stock threshold. Each Variant has its own stock and unit.</p>
    <a href="{{ route('reports.index') }}" class="mt-3 inline-block font-semibold text-amber-700 print:hidden">Back to Reports</a>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr>
                    <th class="px-3 py-3 text-left">Category</th>
                    <th class="px-3 py-3 text-left">Product</th>
                    <th class="px-3 py-3 text-left">Size</th>
                    <th class="px-3 py-3 text-left">Type / series</th>
                    <th class="px-3 py-3 text-left">Thickness</th>
                    <th class="px-3 py-3 text-left">Unit</th>
                    <th class="px-3 py-3 text-right">Current stock</th>
                    <th class="px-3 py-3 text-right">Low-stock threshold</th>
                    <th class="px-3 py-3 text-left">Stock state</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($variants as $variant)
                        <tr data-report-variant="{{ $variant->id }}">
                            <td class="px-3 py-3">{{ $variant->product->category->name }}</td>
                            <td class="px-3 py-3 font-semibold">{{ $variant->product->name }}</td>
                            <td class="px-3 py-3">{{ $variant->size ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $variant->type_series ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $variant->thickness ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $variant->unit }}</td>
                            <td class="px-3 py-3 text-right">{{ $variant->displayCurrentStock() }}</td>
                            <td class="px-3 py-3 text-right">{{ $variant->displayLowStockThreshold() }}</td>
                            <td class="px-3 py-3">{{ bccomp((string) $variant->current_stock, '0.000', 3) === 0 ? 'Out of stock' : 'Low stock' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-600">No low-stock items require attention.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection

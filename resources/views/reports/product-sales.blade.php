@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
    <h1 class="mt-1 text-3xl font-bold">Product Sales Report</h1>
    <p class="mt-2 text-slate-600">Completed sales grouped by historical Product/Variant identity for an inclusive Manila calendar-date range.</p>
    <a href="{{ route('reports.index') }}" class="mt-3 inline-block font-semibold text-amber-700 print:hidden">Back to Reports</a>

    <form method="GET" action="{{ route('reports.product-sales') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_1fr_auto] print:hidden">
        <label><span class="text-sm font-medium">Date from</span><input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">@if (isset($filterErrors['date_from']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_from'] }}</span>@endif</label>
        <label><span class="text-sm font-medium">Date to</span><input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">@if (isset($filterErrors['date_to']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_to'] }}</span>@endif</label>
        <div class="flex items-end gap-2"><button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-500">Apply filters</button><a href="{{ route('reports.product-sales') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Reset</a></div>
    </form>

    @if ($filterErrors !== [])
        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">The report was not run because one or more filters are invalid.</p>
    @else
        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-sm">Historical Product</th><th class="px-4 py-3 text-left text-sm">Variant</th><th class="px-4 py-3 text-left text-sm">Unit</th><th class="px-4 py-3 text-right text-sm">Quantity Sold</th><th class="px-4 py-3 text-right text-sm">Sales Amount</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($rows as $row)
                        @php($variant = collect([$row['size_snapshot'], $row['type_series_snapshot'], $row['thickness_snapshot']])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                        <tr><td class="px-4 py-3 font-semibold">{{ $row['product_name_snapshot'] }}</td><td class="px-4 py-3">{{ $variant }}</td><td class="px-4 py-3">{{ $row['unit_snapshot'] }}</td><td class="px-4 py-3 text-right">{{ $row['quantity'] }}</td><td class="px-4 py-3 text-right font-semibold">₱{{ $row['sales_amount'] }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No completed product sales were recorded in the selected period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</main>
@endsection

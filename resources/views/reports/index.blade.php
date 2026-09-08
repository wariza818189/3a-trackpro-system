@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Management reporting</p>
        <h1 class="mt-1 text-3xl font-bold">Sales Summary</h1>
        <p class="mt-2 text-slate-600">Completed Sales aggregated across an inclusive Manila calendar-date range.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-4">
        <label>
            <span class="text-sm font-medium">Date from</span>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            @if (isset($filterErrors['date_from']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_from'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Date to</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            @if (isset($filterErrors['date_to']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_to'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Cashier</span>
            <select name="cashier" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All cashiers</option>
                @foreach ($cashiers as $cashierOption)
                    <option value="{{ $cashierOption->id }}" @selected($cashier === (string) $cashierOption->id)>{{ $cashierOption->name }}</option>
                @endforeach
            </select>
            @if (isset($filterErrors['cashier']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['cashier'] }}</span>@endif
        </label>
        <div class="flex items-end gap-2">
            <button class="flex-1 rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-500">Apply filters</button>
            <a href="{{ route('reports.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-100">Reset</a>
        </div>
    </form>

    @if ($filterErrors !== [])
        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">The report was not run because one or more filters are invalid.</p>
    @endif

    <section class="mt-6 grid gap-4 sm:grid-cols-2" aria-label="Completed Sales summary">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-semibold text-slate-500">Completed Sales Total</h2><p class="mt-2 text-3xl font-bold">₱{{ $salesTotal }}</p></article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-semibold text-slate-500">Completed Transactions</h2><p class="mt-2 text-3xl font-bold">{{ $transactionCount }}</p></article>
    </section>

    <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <section aria-labelledby="daily-sales-title">
            <h2 id="daily-sales-title" class="text-xl font-bold">Daily Sales</h2>
            <p class="mt-1 text-sm text-slate-600">Every selected Manila calendar day, including days with no completed Sales.</p>
            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-sm">Date</th><th class="px-4 py-3 text-right text-sm">Completed Transactions</th><th class="px-4 py-3 text-right text-sm">Completed Sales Total</th></tr></thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($dailySales as $day)
                                <tr><td class="px-4 py-3"><time datetime="{{ $day['date'] }}">{{ $day['label'] }}</time></td><td class="px-4 py-3 text-right">{{ $day['transaction_count'] }}</td><td class="px-4 py-3 text-right font-semibold">₱{{ $day['sales_total'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">No daily report is available for the invalid range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section aria-labelledby="quantity-unit-title">
            <h2 id="quantity-unit-title" class="text-xl font-bold">Quantity Sold by Unit</h2>
            <p class="mt-1 text-sm text-slate-600">Historical Sale quantities remain separated by unit.</p>
            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-sm">Unit</th><th class="px-4 py-3 text-right text-sm">Quantity Sold</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($quantitiesByUnit as $quantity)
                            <tr><td class="px-4 py-3">{{ $quantity['unit'] }}</td><td class="px-4 py-3 text-right font-semibold">{{ $quantity['quantity_total'] }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-10 text-center text-slate-500">No completed quantities in this report.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
@endsection

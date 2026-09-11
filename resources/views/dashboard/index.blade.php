@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">{{ $today->format('l, F j, Y') }}</p>
        <h1 class="mt-1 text-3xl font-bold">Dashboard</h1>
        <p class="mt-2 text-slate-600">Today’s sales and current inventory alerts for 3A Hardware Store.</p>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Today’s operational summary">
        @foreach ([
            ['Today\'s Sales', '₱'.$todaySalesTotal, 'text-slate-950'],
            ['Transactions Today', (string) $todayTransactionCount, 'text-slate-950'],
            ['Low Stock', (string) $lowStockCount, 'text-amber-800'],
            ['Out of Stock', (string) $outOfStockCount, 'text-red-700'],
        ] as [$label, $value, $valueClass])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-500">{{ $label }}</h2>
                <p class="mt-2 text-3xl font-bold {{ $valueClass }}">{{ $value }}</p>
            </article>
        @endforeach
    </section>

    @if ($sevenDayTrend !== null)
        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="sales-trend-title">
            <div>
                <h2 id="sales-trend-title" class="text-xl font-bold">Seven-day Completed Sales Trend</h2>
                <p class="mt-1 text-sm text-slate-600">Today and the previous six Manila calendar days.</p>
            </div>
            <div class="mt-5 space-y-4">
                @foreach ($sevenDayTrend as $day)
                    <div class="grid gap-2 sm:grid-cols-[5rem_minmax(0,1fr)_11rem] sm:items-center">
                        <time datetime="{{ $day['date'] }}" class="text-sm font-semibold text-slate-700">{{ $day['label'] }}</time>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                            <div class="h-full rounded-full bg-amber-500" style="width: {{ $day['bar_width'] }}%"></div>
                        </div>
                        <p class="text-sm sm:text-right"><span class="font-semibold">₱{{ $day['sales_total'] }}</span> <span class="text-slate-500">· {{ $day['transaction_count'] }} {{ Str::plural('sale', $day['transaction_count']) }}</span></p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-8 grid gap-8 xl:grid-cols-2">
        <section aria-labelledby="recent-sales-title">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="recent-sales-title" class="text-xl font-bold">Recent Completed Sales</h2>
                    <p class="mt-1 text-sm text-slate-600">The five latest completed transactions.</p>
                </div>
                <a href="{{ route('sales.index') }}" class="text-sm font-semibold text-amber-700">Sales History →</a>
            </div>
            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr><th class="px-3 py-3 text-left text-sm">Receipt / Date</th><th class="px-3 py-3 text-left text-sm">Cashier</th><th class="px-3 py-3 text-right text-sm">Items</th><th class="px-3 py-3 text-right text-sm">Total</th><th class="px-3 py-3 text-right text-sm">Action</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($recentSales as $sale)
                                <tr>
                                    <td class="px-3 py-3"><span class="font-semibold">{{ $sale->receiptNumber() }}</span><br><time datetime="{{ $sale->created_at?->toAtomString() }}" class="text-xs text-slate-500">{{ $sale->created_at?->format('M j, Y g:i A') }}</time><br><span class="text-xs font-semibold capitalize text-emerald-700">{{ $sale->status }}</span></td>
                                    <td class="px-3 py-3 text-sm">{{ $sale->recordedBy->name }}</td>
                                    <td class="px-3 py-3 text-right text-sm">{{ $sale->items_count }}</td>
                                    <td class="px-3 py-3 text-right text-sm font-semibold">₱{{ $sale->total_amount }}</td>
                                    <td class="px-3 py-3 text-right"><a href="{{ route('sales.show', $sale->id) }}" class="text-sm font-semibold text-amber-700">View receipt</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No completed Sales yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section aria-labelledby="low-stock-title">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="low-stock-title" class="text-xl font-bold">Low Stock Items</h2>
                    <p class="mt-1 text-sm text-slate-600">Active items at or below their configured threshold.</p>
                </div>
                <a href="{{ route('product-variants.index', ['low_stock' => 1]) }}" class="text-sm font-semibold text-amber-700">View Variants →</a>
            </div>
            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr><th class="px-3 py-3 text-left text-sm">Category / Product</th><th class="px-3 py-3 text-left text-sm">Variant / Unit</th><th class="px-3 py-3 text-right text-sm">Stock / Threshold</th></tr></thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($lowStockItems as $variant)
                                @php($identity = collect([$variant->size, $variant->type_series, $variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                                <tr>
                                    <td class="px-3 py-3"><span class="font-semibold">{{ $variant->product_name }}</span><br><span class="text-xs text-slate-500">{{ $variant->category_name }}</span></td>
                                    <td class="px-3 py-3 text-sm">{{ $identity }}<br><span class="text-xs text-slate-500">{{ $variant->unit }}</span></td>
                                    <td class="px-3 py-3 text-right text-sm"><span @class(['rounded px-2 py-1 font-semibold', 'bg-red-100 text-red-800' => (string) $variant->current_stock === '0.000', 'bg-amber-100 text-amber-900' => (string) $variant->current_stock !== '0.000'])>{{ $variant->displayCurrentStock() }} / {{ $variant->displayLowStockThreshold() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">No low-stock active items.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</main>
@endsection

@php
    $reportGroups = [
        'Sales' => [
            ['route' => 'reports.index', 'title' => 'Sales Summary', 'description' => 'Completed sales across the selected Manila date range.'],
            ['route' => 'reports.product-sales', 'title' => 'Product Sales Report', 'description' => 'Quantity and sales amount by historical Product/Variant.'],
        ],
        'Inventory' => [
            ['route' => 'reports.inventory', 'title' => 'Inventory Report', 'description' => 'Current quantities, units, categories, and stock status.'],
            ['route' => 'reports.low-stock', 'title' => 'Low Stock Report', 'description' => 'Active Variants at or below their stock threshold.'],
            ['route' => 'reports.restocking', 'title' => 'Restocking Report', 'description' => 'Accepted Stock In and Purchase Order receiving history.'],
        ],
        'Procurement' => [
            ['route' => 'reports.pending-purchase-orders', 'title' => 'Pending Purchase Orders Report', 'description' => 'Open Purchase Orders with outstanding demand.'],
            ['route' => 'reports.unfulfilled-items', 'title' => 'Unfulfilled Items Report', 'description' => 'Remaining Purchase Order item quantities.'],
            ['route' => 'reports.damaged-items', 'title' => 'Damaged Items Report', 'description' => 'Recorded damaged receiving evidence.'],
        ],
    ];
@endphp

<nav class="mt-6 space-y-4 print:hidden" aria-label="Reports navigation">
    @foreach ($reportGroups as $group => $reports)
        <section aria-labelledby="reports-group-{{ strtolower($group) }}">
            <h2 id="reports-group-{{ strtolower($group) }}" class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">{{ $group }}</h2>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($reports as $report)
                    @php($active = request()->routeIs($report['route']))
                    <a href="{{ route($report['route']) }}" @if ($active) aria-current="page" @endif
                        class="flex min-h-28 min-w-0 flex-col rounded-xl border p-3.5 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 {{ $active ? 'border-amber-400 bg-amber-50 text-slate-900' : 'border-slate-200 bg-white text-slate-900 hover:border-slate-300 hover:bg-slate-50' }}">
                        <span class="flex items-start justify-between gap-2">
                            <span class="text-sm font-semibold leading-snug">{{ $report['title'] }}</span>
                            @if ($active)
                                <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Current</span>
                            @else
                                <span class="shrink-0 text-slate-400" aria-hidden="true">→</span>
                            @endif
                        </span>
                        <span class="mt-2 text-xs leading-relaxed text-slate-600">{{ $report['description'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</nav>

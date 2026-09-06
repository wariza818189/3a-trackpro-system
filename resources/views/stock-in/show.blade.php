@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <a href="{{ route('stock-in.index') }}" class="text-sm font-medium text-amber-700">← Back to Stock In history</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-3xl font-bold">{{ $restock->restockNumber() }}</h1><p class="mt-1 text-slate-600">Immutable Stock In detail</p></div>
        @if ($admin)<p class="text-xl font-bold">Total: ₱{{ $restock->total_cost }}</p>@endif
    </div>

    <dl class="mt-6 grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2">
        <div><dt class="text-sm text-slate-500">Recorded</dt><dd class="font-medium">{{ $restock->created_at?->format('M j, Y g:i A') }}</dd></div>
        <div><dt class="text-sm text-slate-500">Recorder</dt><dd class="font-medium">{{ $restock->recordedBy->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Reference</dt><dd class="font-medium">{{ $restock->reference_text ?? '—' }}</dd></div>
        <div><dt class="text-sm text-slate-500">Notes</dt><dd class="font-medium">{{ $restock->notes ?? '—' }}</dd></div>
    </dl>

    <div class="mt-6 overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-sm">Product / Variant</th><th class="px-4 py-3 text-left text-sm">Unit</th><th class="px-4 py-3 text-right text-sm">Quantity</th>@if ($admin)<th class="px-4 py-3 text-right text-sm">Unit cost</th><th class="px-4 py-3 text-right text-sm">Line total</th>@endif<th class="px-4 py-3 text-right text-sm">Before</th><th class="px-4 py-3 text-right text-sm">Change</th><th class="px-4 py-3 text-right text-sm">After</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($restock->items as $item)
                        @php($identity = collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                        <tr>
                            <td class="px-4 py-3"><span class="font-medium">{{ $item->product_name_snapshot }}</span><br><span class="text-sm text-slate-500">{{ $identity }}</span></td>
                            <td class="px-4 py-3 text-sm">{{ $item->unit_snapshot }}</td>
                            <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                            @if ($admin)<td class="px-4 py-3 text-right">₱{{ $item->unit_cost }}</td><td class="px-4 py-3 text-right">₱{{ $item->line_total }}</td>@endif
                            <td class="px-4 py-3 text-right">{{ $item->stockMovement?->quantity_before ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $item->stockMovement?->quantity_change ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $item->stockMovement?->quantity_after ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection

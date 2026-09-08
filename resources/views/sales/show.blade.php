@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 print:max-w-none print:p-0">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('sales.index') }}" class="text-sm font-semibold text-amber-700">← Back to Sales History</a>
        <button type="button" onclick="window.print()" class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700">Print receipt</button>
    </div>

    <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none" aria-labelledby="receipt-title">
        <header class="flex flex-wrap items-start justify-between gap-5 border-b border-slate-200 pb-5">
            <div>
                <x-brand-logo />
                <p class="mt-3 text-sm text-slate-500">Sales receipt</p>
                <h1 id="receipt-title" class="text-2xl font-bold">{{ $sale->receiptNumber() }}</h1>
            </div>
            <span @class(['rounded-full px-3 py-1 text-sm font-semibold capitalize', 'bg-emerald-100 text-emerald-800' => $sale->status === \App\Models\Sale::STATUS_COMPLETED, 'bg-red-100 text-red-800' => $sale->status !== \App\Models\Sale::STATUS_COMPLETED])>{{ $sale->status }}</span>
        </header>

        <dl class="grid gap-4 border-b border-slate-200 py-5 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-500">Sale date and time</dt><dd class="font-semibold">{{ $sale->created_at?->format('M j, Y g:i A') }}</dd></div>
            <div><dt class="text-sm text-slate-500">Cashier</dt><dd class="font-semibold">{{ $sale->recordedBy->name }}</dd></div>
        </dl>

        <div class="overflow-x-auto py-5">
            <table class="min-w-full divide-y divide-slate-200">
                <thead>
                    <tr>
                        <th class="py-3 pr-3 text-left text-sm">Product / Variant</th>
                        <th class="px-3 py-3 text-left text-sm">Unit</th>
                        <th class="px-3 py-3 text-right text-sm">Quantity</th>
                        <th class="px-3 py-3 text-right text-sm">Unit price</th>
                        <th class="py-3 pl-3 text-right text-sm">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($sale->items as $item)
                        @php($identity = collect([$item->size_snapshot, $item->type_series_snapshot, $item->thickness_snapshot])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                        <tr>
                            <td class="py-3 pr-3"><span class="font-semibold">{{ $item->product_name_snapshot }}</span><br><span class="text-sm text-slate-500">{{ $identity }}</span></td>
                            <td class="px-3 py-3 text-sm">{{ $item->unit_snapshot }}</td>
                            <td class="px-3 py-3 text-right">{{ $item->quantity }}</td>
                            <td class="px-3 py-3 text-right">₱{{ $item->unit_price }}</td>
                            <td class="py-3 pl-3 text-right font-semibold">₱{{ $item->line_total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <dl class="ml-auto grid max-w-sm gap-2 border-t border-slate-200 pt-5">
            <div class="flex justify-between gap-6 text-lg font-bold"><dt>Total</dt><dd>₱{{ $sale->total_amount }}</dd></div>
            <div class="flex justify-between gap-6"><dt class="text-slate-600">Cash received</dt><dd class="font-semibold">₱{{ $sale->cash_received }}</dd></div>
            <div class="flex justify-between gap-6"><dt class="text-slate-600">Change</dt><dd class="font-semibold">₱{{ $sale->change_amount }}</dd></div>
        </dl>
    </article>
</main>
@endsection

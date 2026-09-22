@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10" data-purchase-order-form>
    <div class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Procurement</p>
        <h1 class="mt-2 text-3xl font-bold">Create Purchase Order</h1>
        <p class="mt-2 text-slate-600">Prioritize low-stock needs or choose any active, initialized variant. Quantities and expected costs are always entered explicitly.</p>
    </div>

    @if (session('purchase_order_confirmation'))
        @php
            $confirmation = session('purchase_order_confirmation');
        @endphp
        <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950" role="status" data-po-confirmation>
            <h2 class="font-bold">{{ $confirmation['replayed'] ? 'Purchase Order already recorded' : 'Purchase Order created' }}</h2>
            <p class="mt-1 text-sm">
                PO #{{ $confirmation['id'] }} · {{ $confirmation['supplier_name'] }} · {{ ucfirst($confirmation['status']) }} ·
                {{ $confirmation['line_count'] }} {{ Str::plural('line', $confirmation['line_count']) }}
            </p>
            @if ($confirmation['replayed'])
                <p class="mt-2 text-sm">The equivalent earlier submission was reused; no duplicate Purchase Order was created.</p>
            @endif
        </section>
    @endif

    @include('purchase-orders._form', [
        'mode' => 'create',
        'formAction' => route('purchase-orders.store'),
        'formMethod' => 'POST',
        'submitLabel' => 'Create Pending Purchase Order',
        'draftRows' => $oldDraftRows,
    ])
</main>
@endsection

@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10" data-purchase-order-form>
    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="text-sm font-semibold text-amber-700">← Back to Purchase Order</a>

    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Procurement</p>
            <h1 class="mt-2 text-3xl font-bold">Edit Purchase Order #{{ $purchaseOrder->id }}</h1>
            <p class="mt-2 text-slate-600">Update the supplier, notes, and complete planned line set while the order remains pending.</p>
        </div>
        <span class="w-fit rounded-full bg-slate-200 px-4 py-2 text-sm font-bold capitalize text-slate-800">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span>
    </div>

    @include('purchase-orders._form', [
        'mode' => 'edit',
        'formAction' => route('purchase-orders.update', $purchaseOrder),
        'formMethod' => 'PATCH',
        'submitLabel' => 'Update Purchase Order',
    ])
</main>
@endsection

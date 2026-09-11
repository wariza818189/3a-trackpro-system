@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Cash checkout</p>
            <h1 class="mt-1 text-3xl font-bold">Point of Sale</h1>
            <p class="mt-2 text-slate-600">Prices, totals, payment, and stock are verified by the server at checkout.</p>
        </div>
        <label class="w-full sm:w-80">
            <span class="sr-only">Search products</span>
            <input type="search" data-pos-search placeholder="Search product or variant" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm focus:border-amber-500 focus:ring-amber-500">
        </label>
    </div>

    @if (session('sale_confirmation'))
        @php($confirmation = session('sale_confirmation'))
        <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950" role="status">
            <h2 class="font-bold">{{ $confirmation['message'] }}</h2>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-5">
                <div><dt class="text-emerald-700">Receipt</dt><dd class="font-semibold">{{ $confirmation['receipt_number'] }}</dd></div>
                <div><dt class="text-emerald-700">Total</dt><dd class="font-semibold">₱{{ $confirmation['total'] }}</dd></div>
                <div><dt class="text-emerald-700">Cash</dt><dd class="font-semibold">₱{{ $confirmation['cash'] }}</dd></div>
                <div><dt class="text-emerald-700">Change</dt><dd class="font-semibold">₱{{ $confirmation['change'] }}</dd></div>
                <div><dt class="text-emerald-700">Distinct items</dt><dd class="font-semibold">{{ $confirmation['item_count'] }}</dd></div>
            </dl>
            <a href="{{ route('sales.show', $confirmation['sale_id']) }}" class="mt-4 inline-flex rounded-lg border border-emerald-400 px-3 py-2 text-sm font-semibold hover:bg-emerald-100">View receipt</a>
        </section>
    @endif

    @if ($pricesRefreshed)
        <p class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900" role="status">
            Prices were refreshed. Review the cart before checking out.
        </p>
    @endif
    @if ($tokenMisuse)
        <p class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900" role="status">
            A fresh checkout token was generated. Review the cart before checking out.
        </p>
    @endif
    @foreach ($unavailableItems as $notice)
        <p class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="status">{{ $notice }}</p>
    @endforeach

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]" data-pos>
        <section aria-labelledby="catalog-title">
            <h2 id="catalog-title" class="text-xl font-bold">Available catalog</h2>
            @if ($variants->isEmpty())
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">No active initialized variants are available.</p>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3" data-pos-catalog>
                    @foreach ($variants as $variant)
                        @php($identity = collect([$variant->size, $variant->type_series, $variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                        @php($outOfStock = bccomp((string) $variant->current_stock, '0.000', 3) <= 0)
                        <article class="rounded-xl border bg-white p-5 shadow-sm" data-pos-product data-search="{{ Str::lower($variant->product->name.' '.$identity.' '.$variant->unit) }}">
                            <h3 class="font-bold">{{ $variant->product->name }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $identity }}</p>
                            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                <div><dt class="text-slate-500">Unit / mode</dt><dd>{{ $variant->unit }} · {{ ucfirst($variant->quantity_mode) }}</dd></div>
                                <div><dt class="text-slate-500">Stock</dt><dd>{{ $variant->displayCurrentStock() }}</dd></div>
                                <div class="col-span-2"><dt class="text-slate-500">Selling price</dt><dd class="text-lg font-bold text-slate-950">₱{{ $variant->selling_price }}</dd></div>
                            </dl>
                            <button type="button"
                                class="mt-4 w-full rounded-lg px-4 py-2 font-semibold {{ $outOfStock ? 'cursor-not-allowed bg-slate-200 text-slate-500' : 'bg-amber-500 text-slate-950 hover:bg-amber-400' }}"
                                data-pos-add
                                data-id="{{ $variant->id }}"
                                data-product="{{ $variant->product->name }}"
                                data-identity="{{ $identity }}"
                                data-unit="{{ $variant->unit }}"
                                data-mode="{{ $variant->quantity_mode }}"
                                data-stock="{{ $variant->current_stock }}"
                                data-price="{{ $variant->selling_price }}"
                                @disabled($outOfStock)>
                                {{ $outOfStock ? 'Out of stock' : 'Add to cart' }}
                            </button>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="h-fit rounded-xl border bg-white p-5 shadow-sm xl:sticky xl:top-6" aria-labelledby="cart-title">
            <h2 id="cart-title" class="text-xl font-bold">Cart</h2>
            <form method="POST" action="{{ route('pos.checkout') }}" class="mt-4" data-pos-form>
                @csrf
                <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                <div class="space-y-3" data-pos-cart>
                    @foreach ($cartRows as $row)
                        @php($variant = $variants->firstWhere('id', $row['product_variant_id']))
                        @if ($variant)
                            @php($identity = collect([$variant->size, $variant->type_series, $variant->thickness])->filter(fn ($value) => $value !== '')->join(' · ') ?: 'Standard')
                            <div class="rounded-lg border border-slate-200 p-3" data-pos-cart-row data-id="{{ $variant->id }}" data-product="{{ $variant->product->name }}" data-identity="{{ $identity }}" data-unit="{{ $variant->unit }}" data-mode="{{ $variant->quantity_mode }}" data-stock="{{ $variant->current_stock }}" data-price="{{ $variant->selling_price }}">
                                <div class="flex justify-between gap-3"><div><p class="font-semibold">{{ $variant->product->name }}</p><p class="text-xs text-slate-500">{{ $identity }} · {{ $variant->unit }}</p></div><button type="button" data-pos-remove class="text-sm font-semibold text-red-700">Remove</button></div>
                                <div class="mt-3 grid grid-cols-2 gap-3"><label><span class="text-xs font-medium text-slate-600">Quantity</span><input name="items[{{ $loop->index }}][quantity]" value="{{ $row['quantity'] }}" inputmode="decimal" required data-pos-quantity class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label><div><span class="text-xs font-medium text-slate-600">Current price</span><p class="mt-2 font-semibold">₱{{ $variant->selling_price }}</p></div></div>
                                <input type="hidden" name="items[{{ $loop->index }}][product_variant_id]" value="{{ $variant->id }}" data-pos-id-input>
                                <input type="hidden" name="items[{{ $loop->index }}][expected_unit_price]" value="{{ $variant->selling_price }}" data-pos-price-input>
                                <p class="mt-2 text-right text-sm text-slate-600">Estimate: <span data-pos-line>—</span></p>
                            </div>
                        @endif
                    @endforeach
                </div>
                <p class="py-5 text-center text-sm text-slate-500" data-pos-empty @if ($cartRows !== []) hidden @endif>The cart is empty.</p>

                <div class="mt-4 border-t border-slate-200 pt-4">
                    <div class="flex justify-between text-lg font-bold"><span>Estimated total</span><output data-pos-total>₱0.00</output></div>
                    <label class="mt-4 block"><span class="text-sm font-semibold">Cash tendered</span><input name="amount_tendered" value="{{ old('amount_tendered', '') }}" inputmode="decimal" required data-pos-tender class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="0.00"></label>
                    <div class="mt-3 flex justify-between text-sm"><span class="text-slate-600">Estimated change</span><output class="font-semibold" data-pos-change>₱0.00</output></div>
                    <button class="mt-5 w-full rounded-lg bg-slate-900 px-4 py-3 font-bold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300" data-pos-checkout>Checkout</button>
                </div>
            </form>
        </aside>
    </div>
</main>
@endsection

@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10" data-purchase-order-create>
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

    @if ($removedOldSelectionCount > 0)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status" data-po-unavailable-notice>
            {{ $removedOldSelectionCount }} previously selected {{ Str::plural('variant', $removedOldSelectionCount) }} became unavailable and {{ $removedOldSelectionCount === 1 ? 'was' : 'were' }} removed. Review the draft before submitting.
        </div>
    @endif

    <form method="POST" action="{{ route('purchase-orders.store') }}" class="mt-6 space-y-6" data-po-form>
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}" data-po-submission-token>

        <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2 md:p-6">
            <label>
                <span class="text-sm font-semibold">Supplier <span class="text-red-600" aria-hidden="true">*</span></span>
                <input name="supplier_name" value="{{ old('supplier_name') }}" maxlength="150" required autocomplete="organization" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            </label>
            <label>
                <span class="text-sm font-semibold">Notes <span class="font-normal text-slate-500">(optional)</span></span>
                <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">{{ old('notes') }}</textarea>
            </label>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.8fr)]">
            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold">Eligible variants</h2>
                        <p class="mt-1 text-sm text-slate-500">Covered and healthy variants remain available for manual purchase planning.</p>
                    </div>
                    <label class="sm:w-72">
                        <span class="sr-only">Search eligible variants</span>
                        <input type="search" data-po-search placeholder="Search product, category, size, or unit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                    </label>
                </div>

                @php
                    $groups = [
                        [
                            'key' => 'uncovered',
                            'title' => 'Priority — No Open PO Coverage',
                            'description' => 'Initialized low-stock variants without an open Purchase Order quantity.',
                            'variants' => $uncovered,
                            'accent' => 'border-red-200 bg-red-50/60',
                        ],
                        [
                            'key' => 'covered',
                            'title' => 'Low Stock — Already Covered',
                            'description' => 'Still visible for planning. Open coverage does not prevent another order.',
                            'variants' => $covered,
                            'accent' => 'border-amber-200 bg-amber-50/60',
                        ],
                        [
                            'key' => 'other',
                            'title' => 'Other Active Initialized Variants',
                            'description' => 'Healthy eligible variants available for manual selection.',
                            'variants' => $otherVariants,
                            'accent' => 'border-slate-200 bg-slate-50/60',
                        ],
                    ];
                @endphp

                <div class="mt-6 space-y-6">
                    @foreach ($groups as $group)
                        <section data-po-group="{{ $group['key'] }}">
                            <h3 class="font-bold text-slate-900">{{ $group['title'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $group['description'] }}</p>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                @forelse ($group['variants'] as $variant)
                                    @php
                                        $identity = collect([$variant->size, $variant->type_series, $variant->thickness])
                                            ->filter(fn ($value) => $value !== '')
                                            ->join(' · ') ?: 'Standard';
                                        $searchText = mb_strtolower(collect([
                                            $variant->product->name,
                                            $variant->product->category->name,
                                            $identity,
                                            $variant->unit,
                                        ])->join(' '));
                                    @endphp
                                    <article class="flex flex-col rounded-xl border p-4 {{ $group['accent'] }}" data-po-variant data-id="{{ $variant->id }}" data-search="{{ $searchText }}">
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $variant->product->category->name }}</p>
                                            <h4 class="mt-1 font-bold">{{ $variant->product->name }}</h4>
                                            <p class="mt-1 text-sm text-slate-600">{{ $identity }} · {{ $variant->unit }}</p>
                                            <p class="mt-2 text-xs text-slate-500">Current stock: {{ $variant->displayCurrentStock() }} {{ $variant->unit }} · {{ ucfirst($variant->quantity_mode) }} quantity</p>
                                            @if ($group['key'] === 'covered')
                                                <p class="mt-2 text-sm font-semibold text-amber-800" data-po-coverage>Open PO coverage: {{ $variant->open_coverage_quantity }} {{ $variant->unit }}</p>
                                            @endif
                                        </div>
                                        <button type="button"
                                            class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                                            data-po-add
                                            data-id="{{ $variant->id }}"
                                            data-product="{{ $variant->product->name }}"
                                            data-identity="{{ $identity }}"
                                            data-unit="{{ $variant->unit }}"
                                            data-mode="{{ $variant->quantity_mode }}">
                                            Add to draft
                                        </button>
                                    </article>
                                @empty
                                    <p class="text-sm text-slate-500" data-po-empty-group>No variants in this group.</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>
                <p class="mt-5 hidden text-sm text-slate-500" data-po-no-search-results>No eligible variants match your search.</p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-6" aria-labelledby="purchase-order-draft-heading">
                <h2 id="purchase-order-draft-heading" class="text-xl font-bold">Order draft</h2>
                <p class="mt-1 text-sm text-slate-500">Enter every quantity and expected unit cost explicitly.</p>

                <div class="mt-5 space-y-4" data-po-draft>
                    @foreach ($oldDraftRows as $index => $row)
                        @php
                            $variant = $row['variant'];
                            $identity = collect([$variant->size, $variant->type_series, $variant->thickness])
                                ->filter(fn ($value) => $value !== '')
                                ->join(' · ') ?: 'Standard';
                        @endphp
                        <div class="rounded-xl border border-slate-200 p-4" data-po-draft-row data-id="{{ $variant->id }}" data-mode="{{ $variant->quantity_mode }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold" data-po-draft-product>{{ $variant->product->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500" data-po-draft-identity>{{ $identity }} · {{ $variant->unit }}</p>
                                </div>
                                <button type="button" class="min-h-11 px-2 text-sm font-semibold text-red-700" data-po-remove>Remove</button>
                            </div>
                            <input type="hidden" name="items[{{ $index }}][product_variant_id]" value="{{ $variant->id }}" data-po-id-input>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label>
                                    <span class="text-xs font-semibold text-slate-600">Ordered quantity</span>
                                    <input name="items[{{ $index }}][ordered_quantity]" value="{{ $row['ordered_quantity'] }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-po-quantity>
                                </label>
                                <label>
                                    <span class="text-xs font-semibold text-slate-600">Expected unit cost</span>
                                    <input name="items[{{ $index }}][expected_unit_cost]" value="{{ $row['expected_unit_cost'] }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-po-cost>
                                </label>
                            </div>
                            <p class="mt-2 text-xs text-slate-500" data-po-mode-hint>{{ $variant->quantity_mode === 'whole' ? 'Enter a whole-number quantity.' : 'Enter a quantity with up to 3 decimal places.' }} Expected cost accepts up to 2 decimal places.</p>
                        </div>
                    @endforeach
                </div>

                <p class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-500 {{ $oldDraftRows->isNotEmpty() ? 'hidden' : '' }}" data-po-empty-draft>
                    No variants selected. Add at least one eligible variant.
                </p>

                <button class="mt-5 w-full rounded-lg bg-amber-600 px-4 py-3 font-bold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-slate-300" data-po-submit @disabled($oldDraftRows->isEmpty())>
                    Create Pending Purchase Order
                </button>
            </section>
        </div>

        <template data-po-draft-template>
            <div class="rounded-xl border border-slate-200 p-4" data-po-draft-row>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold" data-po-draft-product></p>
                        <p class="mt-1 text-xs text-slate-500" data-po-draft-identity></p>
                    </div>
                    <button type="button" class="min-h-11 px-2 text-sm font-semibold text-red-700" data-po-remove>Remove</button>
                </div>
                <input type="hidden" data-po-id-input>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Ordered quantity</span>
                        <input inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-po-quantity>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Expected unit cost</span>
                        <input inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" data-po-cost>
                    </label>
                </div>
                <p class="mt-2 text-xs text-slate-500" data-po-mode-hint></p>
            </div>
        </template>
    </form>
</main>
@endsection

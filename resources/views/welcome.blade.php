@extends('layouts.app')

@section('content')
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <section class="brand-home-hero brand-shadow relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 sm:p-12" aria-labelledby="page-title">
            <div class="mb-8 h-1.5 w-14 rounded-full bg-amber-500" aria-hidden="true"></div>
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-amber-700">3A Hardware Store</p>
            <h1 id="page-title">
                <x-brand-logo size="size-12 sm:size-20" wordmark-class="text-2xl sm:text-5xl" />
            </h1>
            <p class="mt-5 text-xl leading-relaxed text-slate-700">Hardware Store Sales and Inventory Management System</p>
            <p class="mt-6 leading-relaxed text-slate-600">
                Signed in as <span class="font-semibold text-slate-900">{{ auth()->user()->name }}</span>
                ({{ ucfirst(auth()->user()->role) }}).
            </p>
            <p class="mt-3 leading-relaxed text-slate-600">Browse the product catalog or use the navigation to manage it.</p>
            <div class="mt-10 grid gap-4 border-t border-slate-200 pt-8 sm:grid-cols-3">
                @foreach ([['categories.index', 'Categories'], ['products.index', 'Products'], ['product-variants.index', 'Variants']] as [$routeName, $label])
                    <a href="{{ route($routeName) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-6 font-bold shadow-sm transition-colors hover:border-orange-700 hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-orange-700 focus:ring-offset-2">{{ $label }}<span class="text-orange-700" aria-hidden="true">→</span></a>
                @endforeach
            </div>
        </section>
    </main>
@endsection

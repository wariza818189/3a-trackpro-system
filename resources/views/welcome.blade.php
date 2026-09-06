@extends('layouts.app')

@section('content')
    <main class="mx-auto max-w-5xl px-6 py-12">
        <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12" aria-labelledby="page-title">
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-amber-700">3A Hardware Store</p>
            <h1 id="page-title" class="text-4xl font-bold tracking-tight sm:text-5xl">3A TrackPro</h1>
            <p class="mt-5 text-xl leading-relaxed text-slate-700">Hardware Store Sales and Inventory Management System</p>
            <p class="mt-6 leading-relaxed text-slate-600">
                Signed in as <span class="font-semibold text-slate-900">{{ auth()->user()->name }}</span>
                ({{ ucfirst(auth()->user()->role) }}).
            </p>
            <p class="mt-3 leading-relaxed text-slate-600">Browse the product catalog or use the navigation above to manage it.</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach ([['categories.index', 'Categories'], ['products.index', 'Products'], ['product-variants.index', 'Variants']] as [$routeName, $label])
                    <a href="{{ route($routeName) }}" class="rounded-xl border border-slate-200 p-5 font-semibold hover:border-amber-400 hover:bg-amber-50">{{ $label }}</a>
                @endforeach
            </div>
        </section>
    </main>
@endsection

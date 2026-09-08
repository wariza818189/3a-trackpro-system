<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="3A TrackPro hardware store sales and inventory management system.">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand-mark.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    @auth
        @php
            $navigationAdmin = auth()->user()->can('access-admin');
            $navigationSections = [
                [
                    'label' => 'Main',
                    'items' => [
                        ['label' => 'Home', 'route' => 'home', 'active' => 'home'],
                    ],
                ],
                [
                    'label' => 'Sales',
                    'items' => [
                        ['label' => 'POS', 'route' => 'pos.index', 'active' => 'pos.*'],
                        ['label' => 'Sales History', 'route' => 'sales.index', 'active' => 'sales.*'],
                    ],
                ],
                [
                    'label' => 'Catalog',
                    'items' => [
                        ['label' => 'Categories', 'route' => 'categories.index', 'active' => 'categories.*'],
                        ['label' => 'Products', 'route' => 'products.index', 'active' => 'products.*'],
                        ['label' => 'Variants', 'route' => 'product-variants.index', 'active' => 'product-variants.*'],
                    ],
                ],
                [
                    'label' => 'Inventory',
                    'items' => [
                        ['label' => 'Stock In', 'route' => 'stock-in.index', 'active' => 'stock-in.*'],
                        ...($navigationAdmin ? [
                            ['label' => 'Opening Inventory', 'route' => 'opening-inventory.index', 'active' => 'opening-inventory.*'],
                            ['label' => 'Stock Correction', 'route' => 'stock-corrections.index', 'active' => 'stock-corrections.*'],
                        ] : []),
                    ],
                ],
            ];
        @endphp

        <aside data-nav-sidebar class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-slate-200 bg-white shadow-sm lg:flex print:hidden">
            <div class="shrink-0 border-b border-slate-200 px-5 py-5">
                <a href="{{ route('home') }}" aria-label="3A TrackPro Home" class="inline-flex rounded focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                    <x-brand-logo />
                </a>
            </div>
            <x-app-navigation :sections="$navigationSections" label="Primary navigation" mode="desktop" />
        </aside>

        <header data-nav-mobile-bar class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 shadow-sm lg:hidden print:hidden">
            <button type="button" data-nav-toggle aria-controls="mobile-navigation" aria-expanded="false" aria-label="Open navigation" class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                <svg viewBox="0 0 24 24" class="size-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <a href="{{ route('home') }}" aria-label="3A TrackPro Home" class="min-w-0 rounded focus:outline-none focus:ring-2 focus:ring-amber-500">
                <x-brand-logo />
            </a>
        </header>

        <div data-nav-backdrop aria-hidden="true" class="pointer-events-none fixed inset-0 z-40 bg-slate-950/60 opacity-0 transition-opacity duration-200 lg:hidden print:hidden"></div>

        <aside id="mobile-navigation" data-nav-drawer aria-hidden="true" inert class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] -translate-x-full flex-col bg-white shadow-2xl transition-transform duration-200 ease-out lg:hidden print:hidden">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <a href="{{ route('home') }}" data-nav-link aria-label="3A TrackPro Home" class="min-w-0 rounded focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <x-brand-logo />
                </a>
                <button type="button" data-nav-close aria-label="Close navigation" class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <svg viewBox="0 0 24 24" class="size-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>
            <x-app-navigation :sections="$navigationSections" label="Mobile primary navigation" mode="mobile" />
        </aside>
    @endauth

    <div data-app-content @class(['min-w-0', 'lg:pl-60 print:pl-0' => auth()->check()])>
        @if (session('success'))
            <div class="mx-auto mt-6 max-w-7xl px-6 print:hidden" role="status">
                <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mx-auto mt-6 max-w-7xl px-6 print:hidden" role="alert">
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please correct the following:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>

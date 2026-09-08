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
        <header class="border-b border-slate-200 bg-white shadow-sm print:hidden">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <a href="{{ route('home') }}" aria-label="3A TrackPro Home" class="shrink-0 whitespace-nowrap rounded focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <x-brand-logo />
                </a>
                <nav class="flex min-w-0 flex-1 flex-wrap gap-1" aria-label="Primary navigation">
                    @foreach ([
                        'home' => 'Home',
                        'pos.index' => 'POS',
                        'sales.index' => 'Sales History',
                        'categories.index' => 'Categories',
                        'products.index' => 'Products',
                        'product-variants.index' => 'Variants',
                        'stock-in.index' => 'Stock In',
                    ] as $routeName => $label)
                        <a href="{{ route($routeName) }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs(match ($routeName) { 'stock-in.index' => 'stock-in.*', 'pos.index' => 'pos.*', 'sales.index' => 'sales.*', default => $routeName }) ? 'bg-amber-100 text-amber-900' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                    @can('access-admin')
                        <a href="{{ route('opening-inventory.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('opening-inventory.*') ? 'bg-amber-100 text-amber-900' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Opening Inventory
                        </a>
                        <a href="{{ route('stock-corrections.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('stock-corrections.*') ? 'bg-amber-100 text-amber-900' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Stock Correction
                        </a>
                    @endcan
                </nav>
                <div class="min-w-0 max-w-full break-words text-right text-sm sm:max-w-40">
                    <p class="font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-slate-500">{{ ucfirst(auth()->user()->role) }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">Sign out</button>
                </form>
            </div>
        </header>
    @endauth

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
</body>
</html>

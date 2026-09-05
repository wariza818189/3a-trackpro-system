@extends('layouts.app')

@section('content')
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-16">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12" aria-labelledby="page-title">
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-amber-700">3A Hardware Store</p>
            <h1 id="page-title" class="text-4xl font-bold tracking-tight sm:text-5xl">3A TrackPro</h1>
            <p class="mt-5 text-xl leading-relaxed text-slate-700">Hardware Store Sales and Inventory Management System</p>
            <p class="mt-6 leading-relaxed text-slate-600">
                Signed in as <span class="font-semibold text-slate-900">{{ auth()->user()->name }}</span>
                ({{ ucfirst(auth()->user()->role) }}).
            </p>
            <p class="mt-3 leading-relaxed text-slate-600">The application is ready for the next approved workflow stage.</p>

            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                    Sign out
                </button>
            </form>
        </section>
    </main>
@endsection

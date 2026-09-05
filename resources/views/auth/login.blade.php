@extends('layouts.app')

@section('content')
    <main class="mx-auto flex min-h-screen max-w-md items-center px-6 py-16">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-8 shadow-sm" aria-labelledby="login-title">
            <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-amber-700">3A TrackPro</p>
            <h1 id="login-title" class="text-3xl font-bold tracking-tight">Sign in to TrackPro</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">Hardware Store Sales and Inventory Management System</p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">Use your assigned username and password.</p>

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
                    <input
                        id="username"
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        autocomplete="username"
                        maxlength="50"
                        class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 shadow-sm outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-200"
                    >
                    @error('username')
                        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 shadow-sm outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-200"
                    >
                    @error('password')
                        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                    Sign in
                </button>
            </form>
        </section>
    </main>
@endsection

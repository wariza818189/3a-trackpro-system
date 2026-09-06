@extends('layouts.app')

@section('content')
    <main class="brand-login-page flex min-h-screen items-center justify-center px-4 py-8 sm:px-8 sm:py-12">
        <div class="brand-shadow grid w-full max-w-5xl overflow-hidden rounded-3xl border border-slate-200 bg-white lg:grid-cols-2">
            <section class="brand-panel relative overflow-hidden p-7 sm:p-10 lg:flex lg:flex-col lg:justify-between lg:p-12" aria-label="3A TrackPro branding">
                <div class="relative z-10">
                    <x-brand-logo size="size-12 sm:size-16" wordmark-class="text-2xl sm:text-3xl" :inverse="true" />
                    <p class="mt-5 max-w-xs text-sm leading-relaxed text-slate-300">Hardware Store Sales and Inventory Management System</p>
                </div>
                <p class="relative z-10 mt-8 text-3xl font-extrabold leading-tight tracking-tight text-white sm:text-4xl lg:my-20 lg:text-5xl">Track Today.<br><span class="text-amber-400">Build Tomorrow.</span></p>
                <div class="brand-blocks" aria-hidden="true"><span></span><span></span><span></span></div>
            </section>
            <section class="p-7 sm:p-10 lg:self-center lg:p-12" aria-labelledby="login-title">
            <div class="mb-6 h-1 w-10 rounded-full bg-amber-500" aria-hidden="true"></div>
            <h1 id="login-title" class="text-3xl font-bold tracking-tight">Sign in to TrackPro</h1>
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

                <button type="submit" class="w-full rounded-xl bg-orange-700 px-4 py-3 font-bold text-white shadow-sm transition-colors hover:bg-orange-800 focus:outline-none focus:ring-2 focus:ring-orange-700 focus:ring-offset-2">
                    Sign in
                </button>
            </form>
            </section>
        </div>
    </main>
@endsection

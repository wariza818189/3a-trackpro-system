@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-2xl px-6 py-10">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold">Create user</h1>
        <p class="mt-1 text-sm text-slate-600">New accounts start active.</p>
        <form method="POST" action="{{ route('users.store') }}" class="mt-6 space-y-5">
            @csrf
            <div><label for="name" class="block text-sm font-medium">Name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120" class="mt-1 w-full rounded-lg border-slate-300">@error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="username" class="block text-sm font-medium">Username</label><input id="username" name="username" value="{{ old('username') }}" required maxlength="50" autocomplete="off" class="mt-1 w-full rounded-lg border-slate-300">@error('username')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="role" class="block text-sm font-medium">Role</label><select id="role" name="role" class="mt-1 w-full rounded-lg border-slate-300"><option value="staff" @selected(old('role', 'staff') === 'staff')>Staff</option><option value="admin" @selected(old('role') === 'admin')>Admin</option></select>@error('role')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="block text-sm font-medium">Password</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-300">@error('password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="block text-sm font-medium">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-300"></div>
            <div class="flex gap-3"><button class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white">Create user</button><a href="{{ route('users.index') }}" class="rounded-lg border px-4 py-2.5 font-semibold">Cancel</a></div>
        </form>
    </section>
</main>
@endsection

@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-3xl px-6 py-10">
    <div class="mb-6"><a href="{{ route('users.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">← User Management</a><h1 class="mt-2 text-3xl font-bold">Manage {{ $user->name }}</h1><p class="mt-1 text-slate-600">{{ ucfirst($user->role) }} · {{ ucfirst($user->status) }}</p></div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">Profile</h2>
            <form method="POST" action="{{ route('users.update', $user) }}" class="mt-5 space-y-4">
                @csrf @method('PATCH')
                <div><label for="name" class="block text-sm font-medium">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="mt-1 w-full rounded-lg border-slate-300">@error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="username" class="block text-sm font-medium">Username</label><input id="username" name="username" value="{{ old('username', $user->username) }}" required maxlength="50" class="mt-1 w-full rounded-lg border-slate-300">@error('username')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <button class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white">Save profile</button>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">Role</h2>
            @if (auth()->id() === $user->id)
                <p class="mt-3 text-sm text-slate-600">Your Admin role cannot be changed from this account.</p>
            @else
                <form method="POST" action="{{ route('users.role', $user) }}" class="mt-5 flex flex-wrap items-end gap-3">
                    @csrf @method('PATCH')
                    <label class="min-w-48"><span class="block text-sm font-medium">Role</span><select name="role" class="mt-1 w-full rounded-lg border-slate-300"><option value="staff" @selected($user->role === 'staff')>Staff</option><option value="admin" @selected($user->role === 'admin')>Admin</option></select></label>
                    <button class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white">Change role</button>
                </form>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">Account status</h2>
            @if ($user->status === 'disabled')
                <form method="POST" action="{{ route('users.reactivate', $user) }}" class="mt-4">@csrf @method('PATCH')<button class="rounded-lg border border-emerald-300 px-4 py-2.5 font-semibold text-emerald-800">Reactivate account</button></form>
            @elseif (auth()->id() === $user->id)
                <p class="mt-3 text-sm text-slate-600">Your own account cannot be disabled.</p>
            @else
                <form method="POST" action="{{ route('users.archive', $user) }}" class="mt-4" onsubmit="return confirm('Disable this user account?')">@csrf @method('PATCH')<button class="rounded-lg border border-red-300 px-4 py-2.5 font-semibold text-red-700">Disable account</button></form>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">Reset password</h2>
            <form method="POST" action="{{ route('users.password', $user) }}" class="mt-5 space-y-4">
                @csrf @method('PATCH')
                <div><label for="password" class="block text-sm font-medium">New password</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-300">@error('password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="password_confirmation" class="block text-sm font-medium">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-300"></div>
                <button class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white">Reset password</button>
            </form>
        </section>
    </div>
</main>
@endsection

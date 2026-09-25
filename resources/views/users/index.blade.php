@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">User Management</h1>
            <p class="mt-1 text-slate-600">Manage staff and administrator accounts.</p>
        </div>
        <a href="{{ route('users.create') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-700">Create user</a>
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <label class="min-w-64 flex-1">
            <span class="text-sm font-medium">Search name or username</span>
            <input name="q" value="{{ $search }}" maxlength="100" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Search users">
        </label>
        <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr>
                    <th class="px-4 py-3 text-left text-sm">Name</th>
                    <th class="px-4 py-3 text-left text-sm">Username</th>
                    <th class="px-4 py-3 text-left text-sm">Role</th>
                    <th class="px-4 py-3 text-left text-sm">Status</th>
                    <th class="px-4 py-3 text-left text-sm">Created</th>
                    <th class="px-4 py-3 text-right text-sm">Action</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                            <td class="px-4 py-3">{{ $user->username }}</td>
                            <td class="px-4 py-3 capitalize">{{ $user->role }}</td>
                            <td class="px-4 py-3"><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-800' => $user->status === 'active', 'bg-slate-100 text-slate-700' => $user->status === 'disabled'])>{{ ucfirst($user->status) }}</span></td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $user->created_at?->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('users.edit', $user) }}" class="rounded border px-3 py-1.5 text-sm font-semibold">Manage</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-6">{{ $users->links() }}</div>
</main>
@endsection

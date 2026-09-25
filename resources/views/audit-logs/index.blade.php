@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Administration</p>
        <h1 class="mt-1 text-3xl font-bold">Audit Logs</h1>
        <p class="mt-2 text-slate-600">Review recorded account and system activity.</p>
    </div>

    <form method="GET" action="{{ route('audit-logs.index') }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-5">
        <label>
            <span class="text-sm font-medium">Actor</span>
            <select name="user" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All actors</option>
                @foreach ($actors as $actorOption)
                    <option value="{{ $actorOption->id }}" @selected($user === (string) $actorOption->id)>{{ $actorOption->name }} ({{ '@'.$actorOption->username }}){{ $actorOption->status === 'disabled' ? ' — Disabled' : '' }}</option>
                @endforeach
            </select>
            @if (isset($filterErrors['user']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['user'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Action</span>
            <select name="action" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All actions</option>
                @foreach ($actionOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($action === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
            @if (isset($filterErrors['action']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['action'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Date from</span>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            @if (isset($filterErrors['date_from']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_from'] }}</span>@endif
        </label>
        <label>
            <span class="text-sm font-medium">Date to</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            @if (isset($filterErrors['date_to']))<span class="mt-1 block text-sm text-red-700">{{ $filterErrors['date_to'] }}</span>@endif
        </label>
        <div class="flex items-end gap-2">
            <button class="flex-1 rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-500">Apply filters</button>
            <a href="{{ route('audit-logs.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-100">Clear</a>
        </div>
    </form>

    @if ($filterErrors !== [])
        <p class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800" role="alert">Correct the filter errors to view audit records.</p>
    @else
        <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr>
                        <th class="px-3 py-3 text-left text-sm">Timestamp</th>
                        <th class="px-3 py-3 text-left text-sm">Actor</th>
                        <th class="px-3 py-3 text-left text-sm">Action</th>
                        <th class="px-3 py-3 text-left text-sm">Affected record</th>
                        <th class="px-3 py-3 text-left text-sm">Description</th>
                        <th class="px-3 py-3 text-left text-sm">Change summary</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($entries as $entry)
                            @php($log = $entry['log'])
                            <tr data-audit-log-id="{{ $log->id }}" class="align-top">
                                <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-700">
                                    @if ($log->created_at)
                                        <time datetime="{{ $log->created_at->timezone(config('app.timezone'))->toIso8601String() }}">{{ $log->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</time>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    <span class="font-semibold">{{ $log->user?->name ?? 'Unknown actor' }}</span>
                                    @if ($log->user)<span class="block text-slate-600">{{ '@'.$log->user->username }}</span>@endif
                                </td>
                                <td class="px-3 py-3 text-sm font-medium">{{ $entry['action_label'] }}</td>
                                <td class="px-3 py-3 text-sm">
                                    <span class="font-medium">{{ $entry['record_label'] }}</span>
                                    @if ($entry['current_user'])
                                        <span class="block text-slate-600">Current account: {{ $entry['current_user']->name }} ({{ '@'.$entry['current_user']->username }})</span>
                                    @endif
                                </td>
                                <td class="max-w-xs whitespace-pre-wrap break-words px-3 py-3 text-sm">{{ $log->description }}</td>
                                <td class="min-w-48 px-3 py-3 text-sm">
                                    @forelse ($entry['changes'] as $change)
                                        <span class="block break-words"><span class="font-medium">{{ $change['label'] }}:</span>
                                            @if ($change['transition'])
                                                {{ $change['before'] ?? '—' }} → {{ $change['after'] ?? '—' }}
                                            @else
                                                {{ $change['after'] }}
                                            @endif
                                        </span>
                                    @empty
                                        <span class="text-slate-500">—</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-600">{{ $entries->total() === 0 ? ($hasRecords ? 'No audit records match the selected filters.' : 'No audit records have been recorded yet.') : 'No audit records on this page.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $entries->links() }}</div>
    @endif
</main>
@endsection

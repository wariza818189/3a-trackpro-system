@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">Movement History</h1>
    <p class="mt-2 text-slate-600">Recorded stock changes, newest first. Product and variant labels reflect the current catalog. Times are in Manila.</p>
    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    @foreach (['Date / Time', 'Product / Variant', 'Type', 'Quantity Change', 'Before', 'After', 'Reference', 'Reason', 'Performed By'] as $heading)
                        <th scope="col" class="px-3 py-3 text-left text-sm">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($movements as $movement)
                    <tr data-movement-id="{{ $movement['id'] }}">
                        <td class="whitespace-nowrap px-3 py-3 text-sm"><time datetime="{{ $movement['datetime'] }}">{{ $movement['date'] }}</time></td>
                        <td class="px-3 py-3 text-sm"><span class="font-semibold">{{ $movement['product'] }}</span><br>{{ $movement['variant'] }}</td>
                        <td class="px-3 py-3 text-sm">{{ $movement['label'] }}</td>
                        <td class="px-3 py-3 text-right text-sm font-semibold tabular-nums">{{ $movement['change'] }}</td>
                        <td class="px-3 py-3 text-right text-sm tabular-nums">{{ $movement['before'] }}</td>
                        <td class="px-3 py-3 text-right text-sm tabular-nums">{{ $movement['after'] }}</td>
                        <td class="px-3 py-3 text-sm">{{ $movement['reference'] }}</td>
                        <td class="max-w-xs break-words px-3 py-3 text-sm">{{ $movement['reason'] ?? '—' }}</td>
                        <td class="px-3 py-3 text-sm">{{ $movement['actor'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">No stock movement records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $movements->links() }}</div>
</main>
@endsection

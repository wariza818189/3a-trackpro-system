@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Stock In</h1>
            <p class="mt-1 text-slate-600">Immutable history of received inventory.</p>
        </div>
        <a href="{{ route('stock-in.create') }}" class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Record Stock In</a>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm">Restock number</th>
                        <th class="px-4 py-3 text-left text-sm">Date and time</th>
                        <th class="px-4 py-3 text-left text-sm">Recorder</th>
                        <th class="px-4 py-3 text-left text-sm">Reference</th>
                        <th class="px-4 py-3 text-right text-sm">Items</th>
                        @if ($admin)<th class="px-4 py-3 text-right text-sm">Total cost</th>@endif
                        <th class="px-4 py-3 text-right text-sm">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($restocks as $restock)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $restock->restockNumber() }}</td>
                            <td class="px-4 py-3 text-sm">{{ $restock->created_at?->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $restock->recordedBy->name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $restock->reference_text ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm">{{ $restock->items_count }}</td>
                            @if ($admin)<td class="px-4 py-3 text-right text-sm">₱{{ $restock->total_cost }}</td>@endif
                            <td class="px-4 py-3 text-right"><a href="{{ route('stock-in.show', $restock->id) }}" class="rounded border border-amber-400 px-3 py-1.5 text-sm font-medium text-amber-800">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $admin ? 7 : 6 }}" class="px-4 py-10 text-center text-slate-500">No Stock In history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $restocks->links() }}</div>
</main>
@endsection

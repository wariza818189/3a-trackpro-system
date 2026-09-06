@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-3xl font-bold">Categories</h1><p class="mt-1 text-slate-600">Browse product groupings.</p></div>
        @if ($admin)<a href="{{ route('categories.create') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-700">Create category</a>@endif
    </div>
    <form method="GET" action="{{ route('categories.index') }}" class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
        <label class="sm:col-span-2"><span class="text-sm font-medium">Search</span><input name="search" value="{{ $search }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Category name"></label>
        @if ($admin)<label><span class="text-sm font-medium">Status</span><select name="status" class="mt-1 w-full rounded-lg border-slate-300">@foreach (['active' => 'Active', 'archived' => 'Archived', 'all' => 'All'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>@endif
        <div class="flex items-end"><button class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Apply filters</button></div>
    </form>
    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-sm">Name</th><th class="px-4 py-3 text-left text-sm">Products</th>@if($admin)<th class="px-4 py-3 text-left text-sm">Status</th><th class="px-4 py-3 text-right text-sm">Actions</th>@endif</tr></thead><tbody class="divide-y divide-slate-100">
        @forelse ($categories as $category)<tr><td class="px-4 py-3 font-medium">{{ $category->name }}</td><td class="px-4 py-3">{{ $category->products_count }}</td>@if($admin)<td class="px-4 py-3 capitalize">{{ $category->status }}</td><td class="px-4 py-3"><div class="flex justify-end gap-2">@if($category->status === 'active')<a href="{{ route('categories.edit', $category) }}" class="rounded border px-3 py-1.5 text-sm">Edit</a><a href="{{ route('products.create', $category) }}" class="rounded border px-3 py-1.5 text-sm">Add product</a><form method="POST" action="{{ route('categories.archive', $category) }}">@csrf @method('PATCH')<button class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-700">Archive</button></form>@else<form method="POST" action="{{ route('categories.reactivate', $category) }}">@csrf @method('PATCH')<button class="rounded border border-emerald-300 px-3 py-1.5 text-sm text-emerald-700">Reactivate</button></form>@endif</div></td>@endif</tr>@empty<tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No categories found.</td></tr>@endforelse
        </tbody></table></div>
    </div><div class="mt-6">{{ $categories->links() }}</div>
</main>
@endsection

@csrf
@if(isset($category)) @method('PATCH') @endif
<label for="name" class="block text-sm font-medium">Category name</label>
<input id="name" name="name" value="{{ old('name', $category->name ?? '') }}" required maxlength="100" autofocus class="mt-2 w-full rounded-lg border-slate-300">
@error('name')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
<div class="mt-6 flex gap-3"><button class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white">Save category</button><a href="{{ route('categories.index') }}" class="rounded-lg border px-4 py-2.5 font-semibold">Cancel</a></div>

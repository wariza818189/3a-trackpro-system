@extends('layouts.app')
@section('content')<main class="mx-auto max-w-2xl px-6 py-10"><section class="rounded-xl border bg-white p-6"><h1 class="text-2xl font-bold">Create category</h1><form method="POST" action="{{ route('categories.store') }}" class="mt-6">@include('categories._form')</form></section></main>@endsection

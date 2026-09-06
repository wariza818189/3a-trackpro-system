@extends('layouts.app')
@section('content')<main class="mx-auto max-w-2xl px-6 py-10"><section class="rounded-xl border bg-white p-6"><h1 class="text-2xl font-bold">Create product</h1><form method="POST" action="{{ route('products.store',$category) }}" class="mt-6">@include('products._form')</form></section></main>@endsection

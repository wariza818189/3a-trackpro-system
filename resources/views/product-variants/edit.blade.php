@extends('layouts.app')
@section('content')<main class="mx-auto max-w-3xl px-6 py-10"><section class="rounded-xl border bg-white p-6"><h1 class="text-2xl font-bold">Edit product variant</h1><form method="POST" action="{{ route('product-variants.update',$productVariant) }}" class="mt-6">@include('product-variants._form')</form></section></main>@endsection

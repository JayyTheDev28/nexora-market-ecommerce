@extends('layouts.app')

@section('title', 'Edit Product — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="products" />

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">Edit Product</h1>

        <form method="POST" action="{{ route('seller.products.update', $product) }}">
            @csrf
            @method('PUT')

            @include('seller.products._form')

            <div class="max-w-2xl flex gap-3 mt-6">
                <button type="submit" class="flex-1 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('seller.products.index') }}" class="flex-1 text-center border border-outline-variant text-on-surface font-label-md text-label-md px-6 py-3 rounded-full hover:bg-surface-container transition-colors">
                    Cancel
                </a>
            </div>
        </form>

    </div>
</div>
@endsection

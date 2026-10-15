@extends('layouts.app')

@section('title', 'Add Product — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="products" />

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">Add Product</h1>

        <form method="POST" action="{{ route('seller.products.store') }}">
            @csrf

            @include('seller.products._form')

            <div class="max-w-2xl flex gap-3 mt-6">
                <button type="submit" name="publish" value="1" class="flex-1 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-colors">
                    Publish Now
                </button>
                <button type="submit" name="publish" value="0" class="flex-1 border border-outline-variant text-on-surface font-label-md text-label-md px-6 py-3 rounded-full hover:bg-surface-container transition-colors">
                    Save as Draft
                </button>
            </div>
        </form>

    </div>
</div>
@endsection

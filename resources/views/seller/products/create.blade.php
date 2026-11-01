@extends('layouts.app')

@section('title', 'Add New Product — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

        <x-seller-nav active="products" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Header Bar --}}
                <div class="flex items-center justify-between gap-4 mb-6">
                    <div>
                        <a href="{{ route('seller.products.index') }}" class="inline-flex items-center gap-1 text-xs text-primary font-semibold hover:underline mb-1">
                            <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back to Catalog
                        </a>
                        <h1 class="font-headline-lg text-headline-lg text-on-surface">Add New Product</h1>
                        <p class="text-xs text-on-surface-variant mt-0.5">Fill in the details below to create and publish your product listing.</p>
                    </div>
                </div>

                {{-- Product Form --}}
                <form method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data" id="productForm">
                    @csrf
                    @include('seller.products._form')
                </form>

            </div>
        </div>
    </div>
</div>
@endsection

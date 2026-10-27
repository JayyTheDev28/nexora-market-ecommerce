@extends('layouts.app')

@section('title', 'Edit ' . $product->name . ' — Seller Hub')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="products" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <a href="{{ route('seller.products.index') }}" class="inline-flex items-center gap-1 text-xs text-primary font-semibold hover:underline mb-1">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back to Catalog
                </a>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Edit Product</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('products.show', $product->id) }}" target="_blank" class="px-3.5 py-1.5 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                    View Live Page
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('seller.products.update', $product) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @include('seller.products._form')

            <div class="max-w-4xl flex items-center justify-between gap-3 mt-6 pt-4 border-t border-outline-variant">
                <a href="{{ route('seller.products.index') }}" class="px-5 py-2.5 rounded-full border border-outline-variant text-sm font-semibold text-on-surface hover:bg-surface-container transition-colors">
                    Cancel
                </a>
                <div class="flex items-center gap-2">
                    <button type="submit" name="action" value="draft" class="px-5 py-2.5 rounded-full border border-outline-variant text-sm font-bold text-on-surface hover:bg-surface-container transition-colors">
                        Revert to Draft
                    </button>
                    <button type="submit" name="action" value="publish" class="px-6 py-2.5 rounded-full bg-primary text-on-primary text-sm font-bold hover:bg-primary/90 transition-colors shadow-sm">
                        Save Product Changes
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>
@endsection

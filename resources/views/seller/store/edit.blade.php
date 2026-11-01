@extends('layouts.app')

@section('title', 'Store Profile & Settings — Nexora Market')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-seller-nav active="store" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        @if(session('status'))
            <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 font-label-md flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 rounded-2xl bg-error/10 border border-error/30 text-error font-body-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Store Profile & Settings</h1>
                <p class="font-body-md text-on-surface-variant">Manage your public merchant identity, branding, contacts, and vacation schedule.</p>
            </div>
            <a href="{{ route('stores.show', auth()->id()) }}" target="_blank" class="flex items-center gap-2 px-4 py-2 rounded-full border border-primary text-primary hover:bg-primary/10 font-label-md transition-colors w-fit">
                <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                Preview Public Store
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Vacation Mode Card --}}
            <div class="lg:col-span-1">
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 sticky top-6">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span class="material-symbols-outlined text-amber-500 text-[24px]">beach_access</span>
                        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Vacation Mode</h2>
                    </div>

                    <p class="font-body-sm text-xs text-on-surface-variant mb-6">
                        Temporarily pause buyer checkouts and new incoming orders while you are away, traveling, or replenishing stock. Existing orders in progress can still be fulfilled.
                    </p>

                    <form method="POST" action="{{ route('seller.store.vacation') }}">
                        @csrf
                        <div class="space-y-4">
                            <label class="flex items-center justify-between p-3.5 rounded-xl border border-outline-variant bg-surface-container-low cursor-pointer">
                                <div>
                                    <span class="font-label-md text-sm font-bold text-on-surface">Vacation Status</span>
                                    <p class="text-[11px] text-on-surface-variant">Toggle store checkout availability</p>
                                </div>
                                <input type="hidden" name="is_vacation_mode" value="0">
                                <input type="checkbox" name="is_vacation_mode" value="1" class="w-5 h-5 rounded text-primary focus:ring-primary cursor-pointer" {{ old('is_vacation_mode', $profile->is_vacation_mode ?? false) ? 'checked' : '' }}>
                            </label>

                            <div>
                                <label for="vacation_notice" class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                                    Vacation Notice to Buyers
                                </label>
                                <textarea
                                    id="vacation_notice"
                                    name="vacation_notice"
                                    rows="3"
                                    placeholder="e.g. We are closed for inventory restocking from Oct 28 to Nov 2. See you soon!"
                                    class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-outline-variant focus:border-primary focus:outline-none">{{ old('vacation_notice', $profile->vacation_notice ?? '') }}</textarea>
                            </div>

                            <button type="submit" class="w-full py-2.5 px-4 rounded-xl font-label-md text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white transition-colors shadow-sm">
                                Update Vacation Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Main Store Details Form --}}
            <div class="lg:col-span-2">
                <form method="POST" action="{{ route('seller.store.update') }}" enctype="multipart/form-data" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold border-b border-outline-variant pb-3">
                        Brand Identity & Contacts
                    </h2>

                    {{-- Store Banner Preview & Upload --}}
                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-2">Store Banner Image</label>
                        <div class="w-full h-36 rounded-2xl overflow-hidden border border-outline-variant bg-surface-container-low relative group">
                            @if(!empty($profile->banner_path))
                                <img src="{{ Storage::url($profile->banner_path) }}" alt="Banner" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-on-surface-variant bg-surface-container">
                                    <span class="material-symbols-outlined text-[32px] text-outline-variant">panorama</span>
                                    <span class="text-xs mt-1">Recommended: 1200 x 300 px (Max 4MB)</span>
                                </div>
                            @endif
                        </div>
                        <input type="file" name="banner" accept="image/*" class="mt-2 text-xs text-on-surface-variant file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-surface-container file:text-on-surface hover:file:bg-surface-container-high cursor-pointer">
                    </div>

                    {{-- Store Logo Preview & Upload --}}
                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-2">Store Logo / Avatar</label>
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 rounded-2xl overflow-hidden border border-outline-variant bg-surface-container-low shrink-0">
                                @if(!empty($profile->logo_path))
                                    <img src="{{ Storage::url($profile->logo_path) }}" alt="Logo" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-primary/10 text-primary font-bold text-xl">
                                        {{ strtoupper(substr($profile->business_name ?? 'S', 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <input type="file" name="logo" accept="image/*" class="text-xs text-on-surface-variant file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-surface-container file:text-on-surface hover:file:bg-surface-container-high cursor-pointer">
                                <p class="text-[11px] text-on-surface-variant mt-1">Square image 400x400 px, max 2MB (PNG, JPG, WEBP)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Business Name --}}
                    <div>
                        <label for="business_name" class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            Business / Store Name <span class="text-error">*</span>
                        </label>
                        <input
                            type="text"
                            id="business_name"
                            name="business_name"
                            required
                            value="{{ old('business_name', $profile->business_name ?? '') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none">
                    </div>

                    {{-- Description / Bio --}}
                    <div>
                        <label for="description" class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            About Your Store & Bio
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            placeholder="Share your store story, mission, and the kind of authentic products you offer to buyers..."
                            class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm text-on-surface placeholder:text-outline-variant focus:border-primary focus:outline-none">{{ old('description', $profile->description ?? '') }}</textarea>
                    </div>

                    {{-- Contact Phone & Email --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="contact_phone" class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                                Customer Service Phone
                            </label>
                            <input
                                type="text"
                                id="contact_phone"
                                name="contact_phone"
                                placeholder="09xxxxxxxxx or +63 9xx..."
                                value="{{ old('contact_phone', $profile->contact_phone ?? '') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label for="contact_email" class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                                Customer Service Email
                            </label>
                            <input
                                type="email"
                                id="contact_email"
                                name="contact_email"
                                placeholder="support@yourstore.com"
                                value="{{ old('contact_email', $profile->contact_email ?? '') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    {{-- Store Online Shopping Categories --}}
                    <div id="store-categories" class="pt-6 border-t border-outline-variant space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h3 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[20px]">category</span>
                                    Store Selling Categories
                                </h3>
                                <p class="text-xs text-on-surface-variant mt-0.5">
                                    Configure which online shopping categories your store operates in. When creating or updating products, you will only be able to list items within your selected categories.
                                </p>
                            </div>
                            <span class="text-xs font-bold px-3 py-1 rounded-full bg-primary/10 text-primary border border-primary/20 shrink-0 w-fit">
                                {{ count($selectedCategoryIds) }} Active
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-2">
                            @foreach($allCategories as $cat)
                                @php
                                    $isSelected = in_array($cat->id, old('categories', $selectedCategoryIds), true);
                                @endphp
                                <label class="flex items-center gap-3 p-3 rounded-2xl border transition-all cursor-pointer select-none {{ $isSelected ? 'border-primary bg-primary/10 text-primary shadow-xs' : 'border-outline-variant bg-surface hover:bg-surface-container' }}">
                                    <input
                                        type="checkbox"
                                        name="categories[]"
                                        value="{{ $cat->id }}"
                                        {{ $isSelected ? 'checked' : '' }}
                                        class="w-4 h-4 rounded text-primary focus:ring-primary border-outline-variant">
                                    <div class="flex items-center gap-2 truncate">
                                        <span class="material-symbols-outlined text-[20px] shrink-0 {{ $isSelected ? 'text-primary' : 'text-on-surface-variant' }}">{{ $cat->icon ?? 'category' }}</span>
                                        <span class="text-xs font-semibold truncate {{ $isSelected ? 'text-primary' : 'text-on-surface' }}">{{ $cat->name }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-on-surface-variant italic">
                            * Tip: Need to expand into a new niche (e.g. adding Fashion, Electronics, or Groceries)? Check the boxes above and click Save Store Changes.
                        </p>
                    </div>

                    <div class="pt-4 border-t border-outline-variant flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-full font-label-md text-sm font-bold bg-primary text-on-primary hover:bg-primary/90 transition-colors shadow-sm">
                            Save Store Changes
                        </button>
                    </div>

                </form>
            </div>

        </div>

                </div>
        </div>
    </div>
</div>

@endsection

@php
    $product = $product ?? null;
    $isEdit = isset($product) && $product->exists;
    $existingVariants = old('variants_json') ? json_decode(old('variants_json'), true) : ($product->variants_data ?? []);
    if (!is_array($existingVariants)) {
        $existingVariants = [];
    }
    $initialGallery = $product->gallery ?? [];
    $existingCover = $initialGallery[0] ?? ($product->image_url ?? '');
    $sellerName = auth()->user()->sellerProfile?->business_name ?? auth()->user()->full_name ?? 'Your Store';

    // Current category name for preview
    $selectedCatName = 'Select Category';
    $currentCatId = (int) old('category_id', $product->category_id ?? 0);
    foreach ($categories as $cat) {
        if ($cat->id === $currentCatId) {
            $selectedCatName = $cat->name;
            break;
        }
    }
@endphp

@if (isset($errors) && $errors->any())
    <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4 text-error">
        <div class="flex items-center gap-2 mb-1.5">
            <span class="material-symbols-outlined text-[18px]">error</span>
            <p class="font-label-md text-xs font-bold">Please correct the following errors before saving:</p>
        </div>
        <ul class="list-disc list-inside text-xs space-y-1 pl-6">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div
    x-data="sellerProductForm({
        name: @js(old('name', $product->name ?? '')),
        categoryId: @js(old('category_id', $product->category_id ?? '')),
        categoryName: @js($selectedCatName),
        storeCategoryId: @js(old('store_category_id', $product->store_category_id ?? '')),
        description: @js(old('description', $product->description ?? '')),
        price: @js(old('price', $product->price ?? '')),
        comparePrice: @js(old('compare_price', $product->compare_price ?? '')),
        sku: @js(old('sku', $product->sku ?? '')),
        stock: @js(old('stock', $product->stock ?? 0)),
        lowStockThreshold: @js(old('low_stock_threshold', $product->low_stock_threshold ?? 10)),
        weight: @js(old('weight', $product->weight ?? 1.00)),
        dimensions: @js(old('dimensions', $product->dimensions ?? '')),
        maxPurchaseLimit: @js(old('max_purchase_limit', $product->max_purchase_limit ?? '')),
        imageUrl: @js(old('image_url', '')),
        existingImages: @js($initialGallery),
        coverImage: @js($existingCover),
        hasVariations: @js(!empty($existingVariants)),
        sellerName: @js($sellerName),
    })"
    class="w-full">

    {{-- Main Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- LEFT COLUMN: Main Form Sections --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- 1. Basic Information Section --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 sm:p-6 space-y-4 shadow-2xs">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold flex items-center justify-center">1</span>
                            Basic Information
                        </h2>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">Title, classification, and detailed description of your product.</p>
                    </div>
                    <span class="text-[11px] font-semibold text-on-surface-variant flex items-center gap-1" :class="name.trim() && categoryId ? 'text-primary' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" x-text="name.trim() && categoryId ? 'check_circle' : 'radio_button_unchecked'"></span>
                        <span x-text="name.trim() && categoryId ? 'Completed' : 'Required'"></span>
                    </span>
                </div>

                {{-- Product Title --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-label-md text-xs font-semibold text-on-surface">
                            Product Title <span class="text-error">*</span>
                        </label>
                        <span class="text-[10px] text-on-surface-variant" x-text="name.length + ' / 255'"></span>
                    </div>
                    <input
                        type="text"
                        name="name"
                        required
                        maxlength="255"
                        x-model="name"
                        placeholder="e.g. Mechanical Gaming Keyboard 61-Keys RGB Backlit"
                        class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-all">
                </div>

                {{-- Category Selectors --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">
                                Platform Category <span class="text-error">*</span>
                            </label>
                            <a href="{{ route('seller.store.edit') }}#store-categories" target="_blank" class="text-[11px] text-primary hover:underline font-semibold flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[13px]">tune</span> Settings
                            </a>
                        </div>
                        <select
                            name="category_id"
                            required
                            x-model="categoryId"
                            @change="onCategoryChange($event)"
                            class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-all cursor-pointer">
                            <option value="" disabled {{ old('category_id', $product->category_id ?? null) ? '' : 'selected' }}>Select Platform Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ (int) old('category_id', $product->category_id ?? 0) === $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-on-surface-variant mt-1.5">
                            Showing {{ count($categories) }} active categories authorized for your merchant profile.
                        </p>
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            Store Category (Optional)
                        </label>
                        <select
                            name="store_category_id"
                            x-model="storeCategoryId"
                            class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-all cursor-pointer">
                            <option value="">-- No Store Category --</option>
                            @foreach($storeCategories as $sc)
                                <option value="{{ $sc->id }}" {{ (int) old('store_category_id', $product->store_category_id ?? 0) === $sc->id ? 'selected' : '' }}>
                                    {{ $sc->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-on-surface-variant mt-1.5">
                            Used to organize items in your merchant storefront tabs.
                        </p>
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-label-md text-xs font-semibold text-on-surface">
                            Product Description
                        </label>
                        <span class="text-[10px] text-on-surface-variant" x-text="description.length + ' / 5000'"></span>
                    </div>
                    <textarea
                        name="description"
                        rows="4"
                        x-model="description"
                        placeholder="Detail materials, dimensions, warranty, features, and package inclusions..."
                        class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-all leading-relaxed"></textarea>
                </div>
            </div>

            {{-- 2. Product Media & Gallery Section --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 sm:p-6 space-y-4 shadow-2xs">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold flex items-center justify-center">2</span>
                            Product Media
                        </h2>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">High quality square images (1:1). The primary photo represents your listing.</p>
                    </div>
                    <span class="text-[11px] font-semibold flex items-center gap-1" :class="hasAnyPhoto() ? 'text-primary' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" x-text="hasAnyPhoto() ? 'check_circle' : 'radio_button_unchecked'"></span>
                        <span x-text="hasAnyPhoto() ? 'Media Added' : 'Optional'"></span>
                    </span>
                </div>

                {{-- Primary Upload Dropzone --}}
                <div
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="handleDrop($event)"
                    :class="dragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/80 bg-surface-container-low/40 hover:bg-surface-container-low'"
                    class="border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-2"
                    onclick="document.getElementById('fileImagesInput').click()">
                    
                    <input
                        type="file"
                        name="images[]"
                        id="fileImagesInput"
                        multiple
                        accept="image/png,image/jpeg,image/webp,image/jpg"
                        class="hidden"
                        @change="handleFileSelect($event)">
                    
                    <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">cloud_upload</span>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-on-surface">Click to browse or drag photos here</p>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">PNG, JPG, WEBP up to 2MB each. Select multiple files at once.</p>
                    </div>
                </div>

                {{-- External Hosted Image URL (Preserved) --}}
                <div class="pt-1">
                    <div class="flex items-center gap-2 mb-1.5">
                        <label class="font-label-md text-xs font-semibold text-on-surface">
                            External Photo URL (Optional)
                        </label>
                        <span class="text-[10px] text-outline font-normal">Alternative hosted image link</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            name="image_url"
                            x-model="imageUrl"
                            placeholder="https://example.com/product-image.jpg"
                            class="flex-1 rounded-xl border border-outline-variant/80 px-3.5 py-2 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                        <button
                            type="button"
                            x-show="imageUrl"
                            @click="setExternalAsCover()"
                            class="px-3 py-2 rounded-xl border border-outline-variant text-[11px] font-semibold text-primary hover:bg-primary/5 transition-colors shrink-0">
                            Set as Cover
                        </button>
                    </div>
                </div>

                {{-- Photo Gallery Manager: Current & Staged Photos --}}
                <div x-show="allMediaItems.length > 0" class="pt-2 border-t border-outline-variant/60">
                    <div class="flex items-center justify-between mb-2.5">
                        <p class="font-label-md text-xs font-semibold text-on-surface">
                            Gallery Photos (<span x-text="allMediaItems.length"></span>)
                        </p>
                        <span class="text-[10px] text-on-surface-variant">Hover on a photo to set as cover or remove</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                        <template x-for="(item, idx) in allMediaItems" :key="item.id">
                            <div class="relative group rounded-xl overflow-hidden border border-outline-variant aspect-square bg-surface-container flex items-center justify-center shadow-2xs">
                                
                                {{-- Thumbnail Image --}}
                                <img :src="item.url" class="w-full h-full object-cover">

                                {{-- Cover Badge for Index 0 / Cover Image --}}
                                <template x-if="item.url === activeCoverPhoto || (idx === 0 && !activeCoverPhoto)">
                                    <div class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded-md bg-primary text-on-primary text-[9px] font-bold shadow-xs flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[10px]">star</span>
                                        <span>Cover</span>
                                    </div>
                                </template>

                                {{-- Marked for Removal Overlay (Existing Images) --}}
                                <template x-if="item.isExisting && removedExistingUrls.includes(item.url)">
                                    <div class="absolute inset-0 bg-error/90 text-white flex flex-col items-center justify-center p-2 text-center text-[10px] font-bold gap-1">
                                        <span>Marked to Remove</span>
                                        <button type="button" @click.stop="undoRemoveExisting(item.url)" class="px-2 py-0.5 rounded bg-white text-error font-semibold text-[9px] hover:bg-white/90">
                                            Undo
                                        </button>
                                    </div>
                                </template>

                                {{-- Hover Actions Overlay --}}
                                <div x-show="!removedExistingUrls.includes(item.url)" class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1.5 p-2">
                                    <template x-if="item.url !== activeCoverPhoto && !(idx === 0 && !activeCoverPhoto)">
                                        <button
                                            type="button"
                                            @click.stop="makeCover(item.url)"
                                            class="w-full py-1 rounded-md bg-white/90 hover:bg-white text-on-surface text-[10px] font-semibold shadow-xs">
                                            Make Cover
                                        </button>
                                    </template>
                                    
                                    <template x-if="item.isExisting">
                                        <button
                                            type="button"
                                            @click.stop="markRemoveExisting(item.url)"
                                            class="w-full py-1 rounded-md bg-error/90 hover:bg-error text-white text-[10px] font-semibold shadow-xs">
                                            Remove
                                        </button>
                                    </template>

                                    <template x-if="!item.isExisting">
                                        <button
                                            type="button"
                                            @click.stop="removeNewUpload(item.id)"
                                            class="w-full py-1 rounded-md bg-error/90 hover:bg-error text-white text-[10px] font-semibold shadow-xs">
                                            Remove
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Hidden Form Inputs for Removed Images --}}
                    <template x-for="url in removedExistingUrls" :key="url">
                        <input type="hidden" name="remove_images[]" :value="url">
                    </template>
                </div>
            </div>

            {{-- 3. Pricing Section --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 sm:p-6 space-y-4 shadow-2xs">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold flex items-center justify-center">3</span>
                            Pricing
                        </h2>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">Define your retail selling price and optional comparison reference.</p>
                    </div>
                    <span class="text-[11px] font-semibold flex items-center gap-1" :class="parseFloat(price) > 0 ? 'text-primary' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" x-text="parseFloat(price) > 0 ? 'check_circle' : 'radio_button_unchecked'"></span>
                        <span x-text="parseFloat(price) > 0 ? 'Price Set' : 'Required'"></span>
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            Selling Price (₱) <span class="text-error">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-xs font-bold text-outline">₱</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="price"
                                required
                                x-model="price"
                                placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant/80 pl-8 pr-3.5 py-2.5 text-xs font-semibold text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                        </div>
                        <p class="text-[10px] text-on-surface-variant mt-1.5">The final price charged to the customer at checkout.</p>
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            Compare-At Price (₱)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-xs font-bold text-outline">₱</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="compare_price"
                                x-model="comparePrice"
                                placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant/80 pl-8 pr-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                        </div>
                        <p class="text-[10px] text-on-surface-variant mt-1.5">Original strikethrough price to showcase sales discounts.</p>
                    </div>
                </div>

                {{-- Calculated Promotion Callout --}}
                <div x-show="comparePrice && parseFloat(comparePrice) > parseFloat(price) && parseFloat(price) > 0" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 text-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">trending_down</span>
                        <span>Promotional Discount: Buyer saves <strong>₱<span x-text="(parseFloat(comparePrice) - parseFloat(price)).toFixed(2)"></span></strong> (<span x-text="Math.round(((parseFloat(comparePrice) - parseFloat(price)) / parseFloat(comparePrice)) * 100)"></span>% off)</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider bg-emerald-600 text-white px-2 py-0.5 rounded-full">Sale Active</span>
                </div>
            </div>

            {{-- 4. Inventory Section --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 sm:p-6 space-y-4 shadow-2xs">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold flex items-center justify-center">4</span>
                            Inventory & SKU
                        </h2>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">Control available units, stock alerts, and merchant tracking identifiers.</p>
                    </div>
                    <span class="text-[11px] font-semibold flex items-center gap-1" :class="parseInt(stock) >= 0 ? 'text-primary' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" x-text="parseInt(stock) >= 0 ? 'check_circle' : 'radio_button_unchecked'"></span>
                        <span x-text="parseInt(stock) >= 0 ? 'Stock Ready' : 'Required'"></span>
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">
                                Total Available Stock <span class="text-error">*</span>
                            </label>
                            <span x-show="hasVariations" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-primary/10 text-primary text-[10px] font-semibold">
                                <span class="material-symbols-outlined text-[12px]">sync</span> Synced with variants
                            </span>
                        </div>
                        <input
                            type="number"
                            min="0"
                            name="stock"
                            id="main_stock"
                            required
                            x-model="stock"
                            class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                        <p class="text-[10px] text-on-surface-variant mt-1.5" x-show="!hasVariations">Units available for immediate dispatch.</p>
                        <p class="text-[10px] text-primary mt-1.5" x-show="hasVariations">Automatically synchronized from the sum of all variation stock rows below.</p>
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            SKU Code (Stock Keeping Unit)
                        </label>
                        <input
                            type="text"
                            name="sku"
                            x-model="sku"
                            placeholder="e.g. NX-KBD-001"
                            class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs font-mono text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                        <p class="text-[10px] text-on-surface-variant mt-1.5">Internal warehouse tracking code.</p>
                    </div>
                </div>

                {{-- Collapsible Advanced Inventory Rules --}}
                <div class="pt-2 border-t border-outline-variant/50" x-data="{ openRules: false }">
                    <button
                        type="button"
                        @click="openRules = !openRules"
                        class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]" :class="openRules ? 'rotate-180' : ''">expand_more</span>
                        <span x-text="openRules ? 'Hide Advanced Stock Rules' : 'Show Advanced Stock Rules (Alerts & Purchase Limits)'"></span>
                    </button>

                    <div x-show="openRules" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3 pt-3 border-t border-outline-variant/40">
                        <div>
                            <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                                Low Stock Alert Threshold
                            </label>
                            <input
                                type="number"
                                min="0"
                                name="low_stock_threshold"
                                x-model="lowStockThreshold"
                                class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                            <span class="text-[10px] text-on-surface-variant mt-1 block">Triggers dashboard warning when stock falls below this level.</span>
                        </div>

                        <div>
                            <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                                Per-Buyer Purchase Limit
                            </label>
                            <input
                                type="number"
                                min="1"
                                name="max_purchase_limit"
                                x-model="maxPurchaseLimit"
                                placeholder="Leave empty for unlimited"
                                class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                            <span class="text-[10px] text-on-surface-variant mt-1 block">Prevents hoarder bots during flash sales and limited promotions.</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 5. Shipping Logistics Section --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 sm:p-6 space-y-4 shadow-2xs">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold flex items-center justify-center">5</span>
                            Shipping Logistics
                        </h2>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">Weight and dimensions used by the system to compute delivery rates.</p>
                    </div>
                    <span class="text-[11px] font-semibold flex items-center gap-1" :class="parseFloat(weight) > 0 ? 'text-primary' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" x-text="parseFloat(weight) > 0 ? 'check_circle' : 'radio_button_unchecked'"></span>
                        <span x-text="parseFloat(weight) > 0 ? 'Weight Set' : 'Required'"></span>
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            Parcel Weight (kg) <span class="text-error">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="1000"
                                name="weight"
                                required
                                x-model="weight"
                                class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                            <span class="absolute right-3.5 top-2.5 text-xs font-semibold text-outline">kg</span>
                        </div>
                        <p class="text-[10px] text-on-surface-variant mt-1.5">Include packaging material weight for accurate courier dispatch.</p>
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                            Package Dimensions (LxWxH cm)
                        </label>
                        <input
                            type="text"
                            name="dimensions"
                            x-model="dimensions"
                            placeholder="e.g. 35 x 15 x 6 cm"
                            class="w-full rounded-xl border border-outline-variant/80 px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                        <p class="text-[10px] text-on-surface-variant mt-1.5">Optional parcel sizing for oversized courier classification.</p>
                    </div>
                </div>
            </div>

            {{-- 6. Product Variations Section --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 sm:p-6 space-y-4 shadow-2xs">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold flex items-center justify-center">6</span>
                            Product Variations
                        </h2>
                        <p class="text-[11px] text-on-surface-variant mt-0.5">Build variation matrix (colors, styles, sizes) with custom photo, price, and stock.</p>
                    </div>
                    <button
                        type="button"
                        onclick="addVariantRow()"
                        class="px-3.5 py-1.5 rounded-full border border-primary text-primary hover:bg-primary/10 text-xs font-bold transition-colors flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">add</span> Add Single Row
                    </button>
                </div>

                {{-- Option Type Generator Tags (Preserves colors and sizes fields) --}}
                <div class="bg-surface-container-low/60 rounded-xl p-4 border border-outline-variant/60 space-y-3">
                    <p class="text-xs font-bold text-on-surface">Option Types & Combinations Generator</p>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-label-md text-[11px] font-semibold text-on-surface mb-1">
                                Variation Options (Comma separated)
                            </label>
                            <input
                                type="text"
                                name="colors"
                                id="inputColors"
                                value="{{ old('colors', isset($product) ? implode(', ', $product->colors ?? []) : '') }}"
                                placeholder="e.g. Blue, Lavender, Black"
                                class="w-full rounded-lg border border-outline-variant/80 px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                        </div>

                        <div>
                            <label class="block font-label-md text-[11px] font-semibold text-on-surface mb-1">
                                Sizes (Comma separated)
                            </label>
                            <input
                                type="text"
                                name="sizes"
                                id="inputSizes"
                                value="{{ old('sizes', isset($product) ? implode(', ', $product->sizes ?? []) : '') }}"
                                placeholder="e.g. Small, Medium, Large or 61-Key, 87-Key"
                                class="w-full rounded-lg border border-outline-variant/80 px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-1">
                        <button
                            type="button"
                            onclick="generateFromColorsSizes()"
                            class="px-4 py-1.5 rounded-xl bg-surface border border-outline-variant text-on-surface hover:bg-surface-container text-xs font-bold transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer">
                            <span class="material-symbols-outlined text-[16px] text-primary">auto_fix</span>
                            Generate Combinations into Table
                        </button>
                    </div>
                </div>

                {{-- Bulk Edit Bar (Shows when rows exist) --}}
                <div id="bulkEditToolbar" class="bg-primary/5 rounded-xl p-3 border border-primary/20 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-1.5 text-primary font-bold">
                        <span class="material-symbols-outlined text-[18px]">tune</span>
                        <span>Bulk Edit Variants:</span>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <input type="number" step="0.01" id="bulkPriceInp" placeholder="Price (₱)" class="w-24 px-2.5 py-1 rounded-lg border border-outline-variant bg-surface text-xs">
                        <input type="number" min="0" id="bulkStockInp" placeholder="Stock" class="w-20 px-2.5 py-1 rounded-lg border border-outline-variant bg-surface text-xs">
                        <input type="text" id="bulkSkuInp" placeholder="SKU Prefix" class="w-28 px-2.5 py-1 rounded-lg border border-outline-variant bg-surface text-xs font-mono">
                        <button
                            type="button"
                            onclick="applyBulkEdit()"
                            class="px-3 py-1 rounded-lg bg-primary text-on-primary font-bold hover:bg-primary/90 text-xs transition-colors shadow-2xs">
                            Apply to All
                        </button>
                    </div>
                </div>

                {{-- Variation Rows Table --}}
                <div class="overflow-x-auto border border-outline-variant/70 rounded-xl">
                    <table class="w-full text-left text-xs" id="variantsTable">
                        <thead class="bg-surface-container-low font-bold text-on-surface-variant uppercase text-[10px]">
                            <tr>
                                <th class="w-16 px-3 py-2.5 text-center">Photo</th>
                                <th class="px-3 py-2.5">Variation Name</th>
                                <th class="px-3 py-2.5">SKU</th>
                                <th class="w-28 px-3 py-2.5">Price (₱)</th>
                                <th class="w-24 px-3 py-2.5">Stock</th>
                                <th class="w-10 text-center px-2 py-2.5">Action</th>
                            </tr>
                        </thead>
                        <tbody id="variantsTableBody" class="divide-y divide-outline-variant/60">
                            @if(!empty($existingVariants) && is_array($existingVariants))
                                @foreach($existingVariants as $idx => $v)
                                    @php
                                        $vRowId = 'row_' . $idx;
                                        $vImg = $v['image'] ?? ($v['image_url'] ?? '');
                                    @endphp
                                    <tr class="variant-row" data-row-id="{{ $vRowId }}">
                                        <td class="px-2 py-2 text-center align-middle">
                                            <div class="relative w-11 h-11 mx-auto rounded-xl border border-dashed border-outline-variant bg-surface-container-low flex items-center justify-center overflow-hidden group hover:border-primary transition-colors cursor-pointer" onclick="triggerVariantImageUpload(this)" title="Upload variation image">
                                                <input type="file" name="variant_images[{{ $vRowId }}]" accept="image/jpeg,image/png,image/webp,image/jpg" class="v-file-input hidden" onchange="previewVariantImage(this)">
                                                <input type="hidden" class="v-image-url" value="{{ $vImg }}">
                                                <input type="hidden" class="v-row-id" value="{{ $vRowId }}">
                                                
                                                <img class="v-img-preview w-full h-full object-cover {{ !empty($vImg) ? '' : 'hidden' }}" src="{{ $vImg }}" alt="Variant preview">
                                                
                                                <span class="v-img-placeholder material-symbols-outlined text-[18px] text-on-surface-variant group-hover:text-primary {{ !empty($vImg) ? 'hidden' : '' }}">add_photo_alternate</span>
                                                
                                                <button type="button" class="v-img-remove absolute top-0.5 right-0.5 w-4 h-4 bg-error text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity {{ !empty($vImg) ? '' : 'hidden' }}" onclick="event.stopPropagation(); removeVariantImage(this);" title="Remove image">
                                                    <span class="material-symbols-outlined text-[10px]">close</span>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text" class="v-name w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs" value="{{ $v['name'] ?? ($v['attributes']['name'] ?? '') }}" placeholder="e.g. Blue / 61-Key">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text" class="v-sku w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs font-mono" value="{{ $v['sku'] ?? '' }}" placeholder="SKU-BLU-61">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" step="0.01" class="v-price w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs" value="{{ $v['price'] ?? '' }}" placeholder="0.00">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" min="0" class="v-stock w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs" value="{{ $v['stock'] ?? 0 }}" placeholder="0">
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" onclick="this.closest('tr').remove(); syncVariantsJson();" class="p-1 text-error hover:bg-error/10 rounded" title="Remove row">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>

                    {{-- Empty State when no variants --}}
                    <div id="variantsEmptyState" class="p-8 text-center bg-surface-container-lowest text-xs text-on-surface-variant {{ count($existingVariants) > 0 ? 'hidden' : '' }}">
                        <span class="material-symbols-outlined text-[28px] text-outline mb-1 block">tune</span>
                        <p class="font-medium">No variations added yet.</p>
                        <p class="text-[11px] text-outline mt-0.5">Use the tag generator above or click "Add Single Row" to start adding options.</p>
                    </div>
                </div>

                <input type="hidden" name="variants_json" id="variantsJson" value="{{ old('variants_json', isset($product->variants_data) ? json_encode($product->variants_data) : '') }}">
            </div>

        </div>

        {{-- RIGHT COLUMN: Sticky Listing Panel & Real-Time Preview --}}
        <div class="lg:col-span-4 sticky top-6 space-y-5">

            {{-- Listing Status & Requirements Checklist Card --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-outline-variant/60">
                    <span class="font-bold text-xs text-on-surface uppercase tracking-wider">Listing Status</span>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $isEdit && $product->status === 'active' ? 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/30' : 'bg-surface-container text-on-surface-variant border border-outline-variant' }}">
                        {{ $isEdit ? ucfirst($product->status) : 'Draft' }}
                    </span>
                </div>

                {{-- Completion Meter --}}
                <div>
                    <div class="flex items-center justify-between text-xs mb-1.5">
                        <span class="font-semibold text-on-surface">Listing Readiness</span>
                        <span class="font-bold text-primary"><span x-text="completedStepsCount()"></span> / 5 ready</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-300" :style="'width: ' + (completedStepsCount() * 20) + '%'"></div>
                    </div>
                </div>

                {{-- Checklist Items --}}
                <div class="space-y-2 text-xs">
                    <div class="flex items-center gap-2" :class="name.trim() ? 'text-on-surface' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" :class="name.trim() ? 'text-primary' : 'text-outline'">
                            <span x-text="name.trim() ? 'check_circle' : 'radio_button_unchecked'"></span>
                        </span>
                        <span>Product Title defined</span>
                    </div>

                    <div class="flex items-center gap-2" :class="categoryId ? 'text-on-surface' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" :class="categoryId ? 'text-primary' : 'text-outline'">
                            <span x-text="categoryId ? 'check_circle' : 'radio_button_unchecked'"></span>
                        </span>
                        <span>Platform Category assigned</span>
                    </div>

                    <div class="flex items-center gap-2" :class="parseFloat(price) > 0 ? 'text-on-surface' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" :class="parseFloat(price) > 0 ? 'text-primary' : 'text-outline'">
                            <span x-text="parseFloat(price) > 0 ? 'check_circle' : 'radio_button_unchecked'"></span>
                        </span>
                        <span>Selling price configured</span>
                    </div>

                    <div class="flex items-center gap-2" :class="parseInt(stock) >= 0 ? 'text-on-surface' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" :class="parseInt(stock) >= 0 ? 'text-primary' : 'text-outline'">
                            <span x-text="parseInt(stock) >= 0 ? 'check_circle' : 'radio_button_unchecked'"></span>
                        </span>
                        <span>Stock quantity allocated</span>
                    </div>

                    <div class="flex items-center gap-2" :class="parseFloat(weight) > 0 ? 'text-on-surface' : 'text-outline'">
                        <span class="material-symbols-outlined text-[16px]" :class="parseFloat(weight) > 0 ? 'text-primary' : 'text-outline'">
                            <span x-text="parseFloat(weight) > 0 ? 'check_circle' : 'radio_button_unchecked'"></span>
                        </span>
                        <span>Shipping weight entered</span>
                    </div>
                </div>
            </div>

            {{-- Real-Time Marketplace Live Preview Card --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-5 shadow-2xs space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-outline-variant/60">
                    <span class="font-bold text-xs text-on-surface uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-primary">visibility</span>
                        Buyer Card Preview
                    </span>
                    <span class="text-[10px] text-on-surface-variant">Live Render</span>
                </div>

                {{-- Preview Card Replica --}}
                <div class="border border-outline-variant/80 rounded-2xl overflow-hidden bg-surface shadow-xs">
                    
                    {{-- Cover Photo --}}
                    <div class="relative aspect-square bg-surface-container flex items-center justify-center overflow-hidden">
                        <template x-if="activeCoverPhoto">
                            <img :src="activeCoverPhoto" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!activeCoverPhoto">
                            <div class="flex flex-col items-center justify-center text-outline p-4 text-center">
                                <span class="material-symbols-outlined text-[36px] mb-1">image</span>
                                <span class="text-[10px]">Photo Preview</span>
                            </div>
                        </template>

                        {{-- Category Pill --}}
                        <div class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-full bg-surface-container-lowest/90 backdrop-blur-xs border border-outline-variant/60 text-[10px] font-semibold text-on-surface truncate max-w-[140px]" x-text="categoryName || 'Category'">
                        </div>

                        {{-- Stock Status Tag --}}
                        <div class="absolute bottom-2.5 left-2.5 px-2 py-0.5 rounded-md text-[9px] font-bold shadow-xs" :class="parseInt(stock) > 0 ? 'bg-emerald-600 text-white' : 'bg-error text-white'">
                            <span x-text="parseInt(stock) > 0 ? 'In Stock (' + stock + ')' : 'Out of Stock'"></span>
                        </div>
                    </div>

                    {{-- Product Info Details --}}
                    <div class="p-3.5 space-y-2">
                        <div class="flex items-center gap-1.5 text-[11px] text-on-surface-variant">
                            <span class="material-symbols-outlined text-[14px] text-primary">storefront</span>
                            <span class="truncate font-medium" x-text="sellerName"></span>
                            <span class="text-primary text-[10px] font-bold">✓</span>
                        </div>

                        <h4 class="font-bold text-xs text-on-surface line-clamp-2 leading-snug" x-text="name.trim() || 'Your Product Title Goes Here'"></h4>

                        <div class="flex items-baseline gap-2 pt-1 border-t border-outline-variant/50">
                            <span class="text-sm font-extrabold text-primary">
                                ₱<span x-text="formatPrice(price)"></span>
                            </span>
                            <template x-if="comparePrice && parseFloat(comparePrice) > parseFloat(price)">
                                <span class="text-[11px] text-outline line-through">
                                    ₱<span x-text="formatPrice(comparePrice)"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Selling Guidelines Card --}}
            <div class="bg-surface-container-low/50 rounded-2xl border border-outline-variant/60 p-4 space-y-2 text-xs">
                <p class="font-bold text-on-surface flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-primary">lightbulb</span>
                    Marketplace Quality Tips
                </p>
                <ul class="text-[11px] text-on-surface-variant space-y-1.5 pl-4 list-disc">
                    <li>Clear titles with key attributes (brand, model, color) rank higher in searches.</li>
                    <li>Always specify exact package weight to prevent courier adjustment charges.</li>
                    <li>Variations with individual images convert 40% higher with buyers.</li>
                </ul>
            </div>

        </div>

    </div>

    {{-- STICKY BOTTOM ACTION BAR (Preserves Save as Draft and Publish) --}}
    <div class="sticky bottom-0 z-40 bg-surface-container-lowest/95 backdrop-blur-md border-t border-outline-variant py-3.5 px-4 sm:px-8 mt-8 shadow-lg -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            
            <div class="flex items-center gap-2 text-xs">
                <span class="w-2 h-2 rounded-full" :class="name.trim() && categoryId && parseFloat(price) > 0 ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                <span class="font-medium text-on-surface-variant">
                    {{ $isEdit ? 'Editing: ' . $product->name : 'New Listing Draft' }}
                </span>
                <span class="text-outline text-[11px] hidden md:inline">• Verify all required fields before publishing</span>
            </div>

            <div class="flex items-center justify-end gap-2.5">
                <a
                    href="{{ route('seller.products.index') }}"
                    class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                    Cancel
                </a>

                <button
                    type="submit"
                    name="action"
                    value="draft"
                    class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-bold text-on-surface hover:bg-surface-container transition-colors cursor-pointer">
                    {{ $isEdit && $product->status === 'draft' ? 'Save as Draft' : ($isEdit ? 'Revert to Draft' : 'Save as Draft') }}
                </button>

                <button
                    type="submit"
                    name="action"
                    value="publish"
                    class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors shadow-xs cursor-pointer flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">publish</span>
                    <span>{{ $isEdit ? 'Save Product Changes' : 'Publish to Marketplace' }}</span>
                </button>
            </div>

        </div>
    </div>

</div>

<script>
function sellerProductForm(initialData) {
    return {
        name: initialData.name || '',
        categoryId: initialData.categoryId || '',
        categoryName: initialData.categoryName || '',
        storeCategoryId: initialData.storeCategoryId || '',
        description: initialData.description || '',
        price: initialData.price || '',
        comparePrice: initialData.comparePrice || '',
        sku: initialData.sku || '',
        stock: initialData.stock || 0,
        lowStockThreshold: initialData.lowStockThreshold || 10,
        weight: initialData.weight || 1.00,
        dimensions: initialData.dimensions || '',
        maxPurchaseLimit: initialData.maxPurchaseLimit || '',
        imageUrl: initialData.imageUrl || '',
        existingImages: initialData.existingImages || [],
        coverImage: initialData.coverImage || '',
        hasVariations: initialData.hasVariations || false,
        sellerName: initialData.sellerName || 'Merchant',

        dragOver: false,
        newUploads: [],
        removedExistingUrls: [],

        init() {
            // Keep main_stock synchronized if variants already exist
            setTimeout(() => {
                syncVariantsJson();
            }, 100);
        },

        get allMediaItems() {
            const list = [];
            // Existing uploaded gallery photos
            this.existingImages.forEach((url, i) => {
                list.push({
                    id: 'existing_' + i,
                    url: url,
                    isExisting: true
                });
            });
            // Newly selected local files
            this.newUploads.forEach(item => {
                list.push({
                    id: item.id,
                    url: item.url,
                    isExisting: false
                });
            });
            // External image URL if provided and valid
            if (this.imageUrl && this.imageUrl.startsWith('http')) {
                list.push({
                    id: 'external_url',
                    url: this.imageUrl,
                    isExisting: false
                });
            }
            return list;
        },

        get activeCoverPhoto() {
            if (this.coverImage) return this.coverImage;
            const valid = this.allMediaItems.filter(item => !this.removedExistingUrls.includes(item.url));
            return valid.length > 0 ? valid[0].url : '';
        },

        hasAnyPhoto() {
            return this.allMediaItems.some(item => !this.removedExistingUrls.includes(item.url));
        },

        formatPrice(val) {
            const num = parseFloat(val);
            return isNaN(num) ? '0.00' : num.toFixed(2);
        },

        completedStepsCount() {
            let count = 0;
            if (this.name.trim()) count++;
            if (this.categoryId) count++;
            if (parseFloat(this.price) > 0) count++;
            if (parseInt(this.stock) >= 0 && this.stock !== '') count++;
            if (parseFloat(this.weight) > 0) count++;
            return count;
        },

        onCategoryChange(event) {
            const sel = event.target;
            if (sel.selectedIndex >= 0) {
                this.categoryName = sel.options[sel.selectedIndex].text;
            }
        },

        handleFileSelect(event) {
            const files = event.target.files;
            if (!files) return;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const objUrl = URL.createObjectURL(file);
                this.newUploads.push({
                    id: 'new_' + Date.now() + '_' + i,
                    file: file,
                    url: objUrl
                });
            }
        },

        handleDrop(event) {
            this.dragOver = false;
            const dt = event.dataTransfer;
            const fileInput = document.getElementById('fileImagesInput');
            if (dt && dt.files && dt.files.length > 0) {
                fileInput.files = dt.files;
                this.handleFileSelect({ target: fileInput });
            }
        },

        makeCover(url) {
            this.coverImage = url;
        },

        setExternalAsCover() {
            if (this.imageUrl) {
                this.coverImage = this.imageUrl;
            }
        },

        markRemoveExisting(url) {
            if (!this.removedExistingUrls.includes(url)) {
                this.removedExistingUrls.push(url);
            }
            if (this.coverImage === url) {
                this.coverImage = '';
            }
        },

        undoRemoveExisting(url) {
            this.removedExistingUrls = this.removedExistingUrls.filter(u => u !== url);
        },

        removeNewUpload(id) {
            const item = this.newUploads.find(u => u.id === id);
            if (item && this.coverImage === item.url) {
                this.coverImage = '';
            }
            this.newUploads = this.newUploads.filter(u => u.id !== id);
        }
    };
}

// Global variation builder methods
function triggerVariantImageUpload(container) {
    const fileInput = container.querySelector('.v-file-input');
    if (fileInput) fileInput.click();
}

function previewVariantImage(fileInput) {
    if (!fileInput.files || !fileInput.files[0]) return;
    const file = fileInput.files[0];
    const container = fileInput.closest('div');
    const img = container.querySelector('.v-img-preview');
    const placeholder = container.querySelector('.v-img-placeholder');
    const removeBtn = container.querySelector('.v-img-remove');

    const objUrl = URL.createObjectURL(file);
    if (img) {
        img.src = objUrl;
        img.classList.remove('hidden');
    }
    if (placeholder) placeholder.classList.add('hidden');
    if (removeBtn) removeBtn.classList.remove('hidden');

    syncVariantsJson();
}

function removeVariantImage(btn) {
    const container = btn.closest('div');
    const fileInput = container.querySelector('.v-file-input');
    const img = container.querySelector('.v-img-preview');
    const placeholder = container.querySelector('.v-img-placeholder');
    const urlInput = container.querySelector('.v-image-url');

    if (fileInput) fileInput.value = '';
    if (urlInput) urlInput.value = '';
    if (img) {
        img.src = '';
        img.classList.add('hidden');
    }
    if (placeholder) placeholder.classList.remove('hidden');
    btn.classList.add('hidden');

    syncVariantsJson();
}

function addVariantRow(name = '', sku = '', price = '', stock = 0, image = '') {
    const tbody = document.getElementById('variantsTableBody');
    const emptyState = document.getElementById('variantsEmptyState');
    if (emptyState) emptyState.classList.add('hidden');

    const mainPrice = price || (document.querySelector('input[name="price"]')?.value || '');
    const rowId = 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    const tr = document.createElement('tr');
    tr.className = 'variant-row';
    tr.setAttribute('data-row-id', rowId);
    tr.innerHTML = `
        <td class="px-2 py-2 text-center align-middle">
            <div class="relative w-11 h-11 mx-auto rounded-xl border border-dashed border-outline-variant bg-surface-container-low flex items-center justify-center overflow-hidden group hover:border-primary transition-colors cursor-pointer" onclick="triggerVariantImageUpload(this)" title="Upload variation image">
                <input type="file" name="variant_images[${rowId}]" accept="image/jpeg,image/png,image/webp,image/jpg" class="v-file-input hidden" onchange="previewVariantImage(this)">
                <input type="hidden" class="v-image-url" value="${image || ''}">
                <input type="hidden" class="v-row-id" value="${rowId}">
                
                <img class="v-img-preview w-full h-full object-cover ${image ? '' : 'hidden'}" src="${image || ''}" alt="Variant preview">
                
                <span class="v-img-placeholder material-symbols-outlined text-[18px] text-on-surface-variant group-hover:text-primary ${image ? 'hidden' : ''}">add_photo_alternate</span>
                
                <button type="button" class="v-img-remove absolute top-0.5 right-0.5 w-4 h-4 bg-error text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity ${image ? '' : 'hidden'}" onclick="event.stopPropagation(); removeVariantImage(this);" title="Remove image">
                    <span class="material-symbols-outlined text-[10px]">close</span>
                </button>
            </div>
        </td>
        <td class="px-3 py-2">
            <input type="text" class="v-name w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs" value="${name}" placeholder="e.g. Blue / 61-Key">
        </td>
        <td class="px-3 py-2">
            <input type="text" class="v-sku w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs font-mono" value="${sku}" placeholder="SKU-BLU-61">
        </td>
        <td class="px-3 py-2">
            <input type="number" step="0.01" class="v-price w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs" value="${mainPrice}" placeholder="0.00">
        </td>
        <td class="px-3 py-2">
            <input type="number" min="0" class="v-stock w-full rounded-lg border border-outline-variant/80 px-2 py-1 bg-surface text-xs" value="${stock}" placeholder="0">
        </td>
        <td class="px-2 py-2 text-center">
            <button type="button" onclick="this.closest('tr').remove(); syncVariantsJson();" class="p-1 text-error hover:bg-error/10 rounded" title="Remove row">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    tr.querySelectorAll('input').forEach(inp => {
        inp.addEventListener('input', syncVariantsJson);
        inp.addEventListener('change', syncVariantsJson);
    });
    syncVariantsJson();
}

function generateFromColorsSizes() {
    const rawColors = (document.getElementById('inputColors')?.value || '').split(',').map(s => s.trim()).filter(Boolean);
    const rawSizes = (document.getElementById('inputSizes')?.value || '').split(',').map(s => s.trim()).filter(Boolean);
    const mainPrice = document.querySelector('input[name="price"]')?.value || '';
    const mainStock = parseInt(document.querySelector('input[name="stock"]')?.value) || 0;

    let combinations = [];
    if (rawColors.length > 0 && rawSizes.length > 0) {
        rawColors.forEach(c => {
            rawSizes.forEach(s => {
                combinations.push(`${c} / ${s}`);
            });
        });
    } else if (rawColors.length > 0) {
        combinations = rawColors;
    } else if (rawSizes.length > 0) {
        combinations = rawSizes;
    }

    if (combinations.length === 0) {
        alert('Please enter at least one Variation or Size option in the boxes above first.');
        return;
    }

    const perStock = Math.max(1, Math.floor(mainStock / combinations.length));
    combinations.forEach(combo => {
        addVariantRow(combo, '', mainPrice, perStock);
    });
}

function applyBulkEdit() {
    const bulkPrice = document.getElementById('bulkPriceInp')?.value;
    const bulkStock = document.getElementById('bulkStockInp')?.value;
    const bulkSku = document.getElementById('bulkSkuInp')?.value;

    const rows = document.querySelectorAll('.variant-row');
    if (rows.length === 0) {
        alert('No variation rows exist yet. Add variations first.');
        return;
    }

    rows.forEach((r, idx) => {
        if (bulkPrice !== '') {
            const p = r.querySelector('.v-price');
            if (p) p.value = bulkPrice;
        }
        if (bulkStock !== '') {
            const s = r.querySelector('.v-stock');
            if (s) s.value = bulkStock;
        }
        if (bulkSku !== '') {
            const k = r.querySelector('.v-sku');
            if (k) k.value = `${bulkSku}-${idx + 1}`;
        }
    });

    syncVariantsJson();
}

function syncVariantsJson() {
    const rows = document.querySelectorAll('.variant-row');
    const emptyState = document.getElementById('variantsEmptyState');
    if (emptyState) {
        if (rows.length === 0) {
            emptyState.classList.remove('hidden');
        } else {
            emptyState.classList.add('hidden');
        }
    }

    const list = [];
    let totalStock = 0;
    rows.forEach(r => {
        const rowIdInp = r.querySelector('.v-row-id');
        const nameInp = r.querySelector('.v-name');
        const skuInp = r.querySelector('.v-sku');
        const priceInp = r.querySelector('.v-price');
        const stockInp = r.querySelector('.v-stock');
        const urlInp = r.querySelector('.v-image-url');
        const fileInp = r.querySelector('.v-file-input');

        const rowId = rowIdInp ? rowIdInp.value : '';
        const name = nameInp ? nameInp.value.trim() : '';
        const sku = skuInp ? skuInp.value.trim() : '';
        const price = priceInp ? (parseFloat(priceInp.value) || 0) : 0;
        const stock = stockInp ? (parseInt(stockInp.value) || 0) : 0;
        const imageUrl = urlInp ? urlInp.value : '';
        const hasFile = fileInp && fileInp.files && fileInp.files.length > 0;

        if (name || sku) {
            list.push({
                row_id: rowId,
                name: name,
                sku: sku,
                price: price,
                stock: stock,
                image_url: imageUrl,
                has_file: hasFile
            });
            totalStock += stock;
        }
    });

    const hidden = document.getElementById('variantsJson');
    if (hidden) {
        hidden.value = list.length > 0 ? JSON.stringify(list) : '';
    }

    // Synchronize total stock into main stock input if variants exist
    if (list.length > 0) {
        const mainStockInp = document.getElementById('main_stock');
        if (mainStockInp) {
            mainStockInp.value = totalStock;
            mainStockInp.dispatchEvent(new Event('input'));
        }
    }
}

document.querySelectorAll('.variant-row input').forEach(inp => {
    inp.addEventListener('input', syncVariantsJson);
    inp.addEventListener('change', syncVariantsJson);
});

// Always sync right before submitting form
document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', syncVariantsJson);
});
</script>

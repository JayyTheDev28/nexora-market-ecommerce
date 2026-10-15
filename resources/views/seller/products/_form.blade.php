@if ($errors->any())
    <div class="mb-6 bg-error-container border border-error/30 rounded-2xl p-4">
        <p class="font-label-md text-label-md text-on-error-container">Please fix the following:</p>
        <ul class="list-disc list-inside font-body-sm text-body-sm text-on-error-container">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-5 max-w-2xl">

    <div class="flex flex-col gap-2">
        <label class="font-label-md text-label-md text-on-surface-variant">Product Name</label>
        <input type="text" name="name" required value="{{ old('name', $product->name ?? '') }}" placeholder="e.g. Classic Leather Tote"
            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
    </div>

    <div class="flex flex-col gap-2">
        <label class="font-label-md text-label-md text-on-surface-variant">Category</label>
        <select name="category_id" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="" disabled {{ old('category_id', $product->category_id ?? null) ? '' : 'selected' }}>Select a category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (int) old('category_id', $product->category_id ?? 0) === $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex flex-col gap-2">
        <label class="font-label-md text-label-md text-on-surface-variant">Description</label>
        <textarea name="description" rows="4" placeholder="What makes this product worth buying?"
            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">{{ old('description', $product->description ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
            <label class="font-label-md text-label-md text-on-surface-variant">Price (₱)</label>
            <input type="number" step="0.01" min="0" name="price" required value="{{ old('price', $product->price ?? '') }}"
                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div class="flex flex-col gap-2">
            <label class="font-label-md text-label-md text-on-surface-variant">Compare-at Price (₱) <span class="text-on-surface-variant font-normal">— optional, shows as a strikethrough discount</span></label>
            <input type="number" step="0.01" min="0" name="compare_price" value="{{ old('compare_price', $product->compare_price ?? '') }}"
                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
    </div>

    <div class="flex flex-col gap-2">
        <label class="font-label-md text-label-md text-on-surface-variant">Stock Quantity</label>
        <input type="number" min="0" name="stock" required value="{{ old('stock', $product->stock ?? 0) }}"
            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
    </div>

    <div class="flex flex-col gap-2">
        <label class="font-label-md text-label-md text-on-surface-variant">Image URL <span class="text-on-surface-variant font-normal">— optional</span></label>
        <input type="url" name="image_url" value="{{ old('image_url', $product->gallery[0] ?? '') }}" placeholder="https://…"
            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
        <p class="font-body-sm text-body-sm text-on-surface-variant">A proper image upload comes in a later phase — paste a direct image link for now.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
            <label class="font-label-md text-label-md text-on-surface-variant">Colors <span class="text-on-surface-variant font-normal">— comma-separated, optional</span></label>
            <input type="text" name="colors" value="{{ old('colors', isset($product) ? implode(', ', $product->colors ?? []) : '') }}" placeholder="e.g. Black, White, Red"
                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div class="flex flex-col gap-2">
            <label class="font-label-md text-label-md text-on-surface-variant">Sizes <span class="text-on-surface-variant font-normal">— comma-separated, optional</span></label>
            <input type="text" name="sizes" value="{{ old('sizes', isset($product) ? implode(', ', $product->sizes ?? []) : '') }}" placeholder="e.g. S, M, L, XL"
                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
    </div>

</div>

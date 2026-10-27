@if ($errors->any())
    <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4 text-error">
        <p class="font-label-md font-bold mb-1">Please correct the following errors:</p>
        <ul class="list-disc list-inside text-xs space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-6 max-w-4xl">

    {{-- Basic Info Section --}}
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 space-y-4 shadow-sm">
        <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-2 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[20px]">info</span>
            Basic Information
        </h2>

        <div>
            <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                Product Title <span class="text-error">*</span>
            </label>
            <input type="text" name="name" required value="{{ old('name', $product->name ?? '') }}" placeholder="e.g. Men's Waterproof Outdoor Hiking Jacket"
                class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Platform Category <span class="text-error">*</span>
                </label>
                <select name="category_id" required class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
                    <option value="" disabled {{ old('category_id', $product->category_id ?? null) ? '' : 'selected' }}>Select Platform Category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (int) old('category_id', $product->category_id ?? 0) === $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Custom Store Category (Optional)
                </label>
                <select name="store_category_id" class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
                    <option value="">-- No Store Category --</option>
                    @foreach($storeCategories as $sc)
                        <option value="{{ $sc->id }}" {{ (int) old('store_category_id', $product->store_category_id ?? 0) === $sc->id ? 'selected' : '' }}>
                            {{ $sc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                Product Description
            </label>
            <textarea name="description" rows="5" placeholder="Detailed product specifications, materials, features, and warranty..."
                class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">{{ old('description', $product->description ?? '') }}</textarea>
        </div>
    </div>

    {{-- Gallery & Images Section --}}
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 space-y-4 shadow-sm">
        <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-2 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[20px]">photo_library</span>
            Product Media & Gallery
        </h2>

        {{-- Existing Gallery Images --}}
        @if(isset($product) && !empty($product->gallery))
            <div>
                <p class="font-label-md text-xs font-semibold text-on-surface mb-2">Current Gallery Images</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                    @foreach($product->gallery as $index => $img)
                        <div class="relative group rounded-xl overflow-hidden border border-outline-variant aspect-square bg-surface-container">
                            <img src="{{ $img }}" class="w-full h-full object-cover">
                            <label class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center text-white text-xs cursor-pointer p-2 text-center">
                                <input type="checkbox" name="remove_images[]" value="{{ $img }}" class="rounded text-error focus:ring-error w-4 h-4 mb-1">
                                <span>Remove</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-on-surface-variant mt-1.5">Check any image to remove it upon saving.</p>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Upload Photos (Multiple)
                </label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full text-xs text-on-surface-variant file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-surface-container file:text-on-surface hover:file:bg-surface-container-high cursor-pointer">
                <p class="text-[11px] text-on-surface-variant mt-1">PNG, JPG, WEBP up to 2MB each. You can select multiple files.</p>
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Or Add Image URL
                </label>
                <input type="text" name="image_url" value="{{ old('image_url') }}" placeholder="https://example.com/product.jpg"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                <p class="text-[11px] text-on-surface-variant mt-1">External hosted product photo link.</p>
            </div>
        </div>
    </div>

    {{-- Pricing & Inventory Section --}}
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 space-y-4 shadow-sm">
        <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-2 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[20px]">payments</span>
            Pricing, Stock & Shipping Logistics
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Selling Price (₱) <span class="text-error">*</span>
                </label>
                <input type="number" step="0.01" min="0" name="price" required value="{{ old('price', $product->price ?? '') }}" placeholder="0.00"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Compare-At Price (₱)
                </label>
                <input type="number" step="0.01" min="0" name="compare_price" value="{{ old('compare_price', $product->compare_price ?? '') }}" placeholder="0.00"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
                <span class="text-[10px] text-on-surface-variant">Original strikethrough price</span>
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    SKU Code
                </label>
                <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="e.g. NX-JKT-001"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary font-mono text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Total Inventory Stock <span class="text-error">*</span>
                </label>
                <input type="number" min="0" name="stock" id="main_stock" required value="{{ old('stock', $product->stock ?? 0) }}"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Low Stock Alert Threshold
                </label>
                <input type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 10) }}"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
                <span class="text-[10px] text-on-surface-variant">Triggers low-stock warning</span>
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Per-Buyer Purchase Limit
                </label>
                <input type="number" min="1" name="max_purchase_limit" value="{{ old('max_purchase_limit', $product->max_purchase_limit ?? '') }}" placeholder="Optional limit"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
                <span class="text-[10px] text-on-surface-variant">Prevents bulk hoarders</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-outline-variant/60">
            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Parcel Shipping Weight (kg) <span class="text-error">*</span>
                </label>
                <input type="number" step="0.01" min="0.01" name="weight" value="{{ old('weight', $product->weight ?? 1.00) }}" required
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
                <span class="text-[10px] text-on-surface-variant">Used to compute accurate courier shipping fees</span>
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1.5">
                    Package Dimensions (LxWxH cm)
                </label>
                <input type="text" name="dimensions" value="{{ old('dimensions', $product->dimensions ?? '') }}" placeholder="e.g. 30 x 20 x 10 cm"
                    class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm text-on-surface bg-surface focus:outline-none focus:border-primary">
            </div>
        </div>
    </div>

    {{-- Product Variations Matrix Section --}}
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 space-y-4 shadow-sm">
        <div class="flex items-center justify-between border-b border-outline-variant pb-2">
            <div>
                <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">tune</span>
                    Product Variations (Optional)
                </h2>
                <p class="text-xs text-on-surface-variant">Enable for products with options like Size, Color, or Material with separate price/stock.</p>
            </div>
            <button type="button" onclick="addVariantRow()" class="px-3.5 py-1.5 rounded-full border border-primary text-primary hover:bg-primary/10 text-xs font-bold transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">add</span> Add Variant
            </button>
        </div>

        {{-- Comma-separated quick tags for colors/sizes --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Color Options (Comma separated)</label>
                <input type="text" name="colors" id="inputColors" value="{{ old('colors', isset($product) ? implode(', ', $product->colors ?? []) : '') }}" placeholder="e.g. Jet Black, Navy Blue, Crimson"
                    class="w-full rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Size Options (Comma separated)</label>
                <input type="text" name="sizes" id="inputSizes" value="{{ old('sizes', isset($product) ? implode(', ', $product->sizes ?? []) : '') }}" placeholder="e.g. Small, Medium, Large, XL"
                    class="w-full rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
            </div>
        </div>

        {{-- Variant Table --}}
        <div class="overflow-x-auto border border-outline-variant rounded-xl mt-3">
            <table class="w-full text-left text-xs" id="variantsTable">
                <thead class="bg-surface-container-low font-bold text-on-surface-variant uppercase">
                    <tr>
                        <th class="px-3 py-2.5">Variation Name</th>
                        <th class="px-3 py-2.5">SKU</th>
                        <th class="px-3 py-2.5">Price (₱)</th>
                        <th class="px-3 py-2.5">Stock</th>
                        <th class="w-12 text-center px-2 py-2.5">Action</th>
                    </tr>
                </thead>
                <tbody id="variantsTableBody" class="divide-y divide-outline-variant">
                    @php
                        $existingVariants = old('variants_json') ? json_decode(old('variants_json'), true) : ($product->variants_data ?? []);
                    @endphp
                    @if(!empty($existingVariants) && is_array($existingVariants))
                        @foreach($existingVariants as $idx => $v)
                            <tr class="variant-row">
                                <td class="px-3 py-2">
                                    <input type="text" class="v-name w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs" value="{{ $v['name'] ?? ($v['attributes']['name'] ?? '') }}" placeholder="e.g. Black / XL">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" class="v-sku w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs font-mono" value="{{ $v['sku'] ?? '' }}" placeholder="SKU-BLK-XL">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="0.01" class="v-price w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs" value="{{ $v['price'] ?? '' }}" placeholder="0.00">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" min="0" class="v-stock w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs" value="{{ $v['stock'] ?? 0 }}" placeholder="0">
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" onclick="this.closest('tr').remove(); syncVariantsJson();" class="p-1 text-error hover:bg-error/10 rounded">
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <input type="hidden" name="variants_json" id="variantsJson" value="{{ old('variants_json', isset($product->variants_data) ? json_encode($product->variants_data) : '') }}">
    </div>

</div>

<script>
function addVariantRow(name = '', sku = '', price = '', stock = 0) {
    const tbody = document.getElementById('variantsTableBody');
    const tr = document.createElement('tr');
    tr.className = 'variant-row';
    tr.innerHTML = `
        <td class="px-3 py-2">
            <input type="text" class="v-name w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs" value="${name}" placeholder="e.g. Red / M">
        </td>
        <td class="px-3 py-2">
            <input type="text" class="v-sku w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs font-mono" value="${sku}" placeholder="SKU-RED-M">
        </td>
        <td class="px-3 py-2">
            <input type="number" step="0.01" class="v-price w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs" value="${price}" placeholder="0.00">
        </td>
        <td class="px-3 py-2">
            <input type="number" min="0" class="v-stock w-full rounded-lg border border-outline-variant px-2 py-1 bg-surface text-xs" value="${stock}" placeholder="0">
        </td>
        <td class="px-2 py-2 text-center">
            <button type="button" onclick="this.closest('tr').remove(); syncVariantsJson();" class="p-1 text-error hover:bg-error/10 rounded">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    tr.querySelectorAll('input').forEach(inp => {
        inp.addEventListener('input', syncVariantsJson);
    });
    syncVariantsJson();
}

function syncVariantsJson() {
    const rows = document.querySelectorAll('.variant-row');
    const list = [];
    let totalStock = 0;
    rows.forEach(r => {
        const name = r.querySelector('.v-name').value.trim();
        const sku = r.querySelector('.v-sku').value.trim();
        const price = parseFloat(r.querySelector('.v-price').value) || 0;
        const stock = parseInt(r.querySelector('.v-stock').value) || 0;
        if (name || sku) {
            list.push({ name, sku, price, stock });
            totalStock += stock;
        }
    });

    document.getElementById('variantsJson').value = list.length > 0 ? JSON.stringify(list) : '';
    if (list.length > 0 && totalStock > 0) {
        const mainStockInp = document.getElementById('main_stock');
        if (mainStockInp && !mainStockInp.dataset.userEdited) {
            mainStockInp.value = totalStock;
        }
    }
}

document.querySelectorAll('.variant-row input').forEach(inp => {
    inp.addEventListener('input', syncVariantsJson);
});

document.getElementById('main_stock')?.addEventListener('input', function() {
    this.dataset.userEdited = 'true';
});
</script>

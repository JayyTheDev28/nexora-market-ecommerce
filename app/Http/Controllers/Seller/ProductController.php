<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $seller = $request->user();

        $query = $seller->products()->with(['category', 'storeCategory'])->latest();

        // Status filter
        if ($status = $request->query('status')) {
            if ($status === 'low_stock') {
                $query->where('status', 'active')
                      ->whereColumn('stock', '<=', 'low_stock_threshold')
                      ->where('stock', '>', 0);
            } elseif ($status === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            } else {
                $query->where('status', $status);
            }
        }

        // Search filter
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Store category filter
        if ($storeCatId = $request->query('store_category')) {
            $query->where('store_category_id', $storeCatId);
        }

        // Platform category filter
        if ($catId = $request->query('category')) {
            $query->where('category_id', $catId);
        }

        $products = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => $seller->products()->count(),
            'active' => $seller->products()->where('status', 'active')->count(),
            'draft' => $seller->products()->where('status', 'draft')->count(),
            'archived' => $seller->products()->where('status', 'archived')->count(),
            'low_stock' => $seller->products()->where('status', 'active')->whereColumn('stock', '<=', 'low_stock_threshold')->where('stock', '>', 0)->count(),
            'out_of_stock' => $seller->products()->where('stock', '<=', 0)->count(),
        ];

        $storeCategories = StoreCategory::where('seller_id', $seller->id)->orderBy('name')->get();
        $platformCategories = Category::orderBy('name')->get();

        return view('seller.products.index', compact('products', 'counts', 'storeCategories', 'platformCategories'));
    }

    public function create(Request $request): View
    {
        $categories = $request->user()->getEffectiveSellerCategories();
        $storeCategories = StoreCategory::where('seller_id', $request->user()->id)->orderBy('name')->get();

        return view('seller.products.create', compact('categories', 'storeCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isDraft = $request->input('action') === 'draft';
        $validated = $this->validateProductData($request, null, $isDraft);

        $productData = $this->prepareProductData($request, $validated, null);
        $productData['status'] = $isDraft ? 'draft' : 'active';
        $productData['slug'] = $this->uniqueSlug($productData['name']);

        $product = $request->user()->products()->create($productData);

        $msg = $isDraft
            ? "\"{$product->name}\" was saved as draft."
            : "\"{$product->name}\" was published and is now live on the marketplace.";

        return redirect()->route('seller.products.index')->with('status', $msg);
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeOwner($request, $product);

        $categories = $request->user()->getEffectiveSellerCategories();
        $storeCategories = StoreCategory::where('seller_id', $request->user()->id)->orderBy('name')->get();

        return view('seller.products.edit', compact('product', 'categories', 'storeCategories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        $isDraft = $request->input('action') === 'draft';
        $validated = $this->validateProductData($request, $product, $isDraft);

        $productData = $this->prepareProductData($request, $validated, $product);
        if ($isDraft) {
            $productData['status'] = 'draft';
        } elseif ($product->status === 'draft') {
            $productData['status'] = 'active';
        }

        $product->update($productData);

        return redirect()->route('seller.products.index')
            ->with('status', "\"{$product->name}\" was updated successfully.");
    }

    public function duplicate(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        $copy = $product->replicate(['slug', 'status']);
        $copy->name = "{$product->name} (Copy)";
        $copy->slug = $this->uniqueSlug($copy->name);
        $copy->status = 'draft';
        if ($product->sku) {
            $copy->sku = "{$product->sku}-COPY";
        }
        $copy->save();

        return redirect()->route('seller.products.edit', $copy)
            ->with('status', "Duplicated \"{$product->name}\" into draft. You can now modify and publish it.");
    }

    public function archive(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        $product->update(['status' => 'archived']);

        return back()->with('status', "\"{$product->name}\" was archived and is hidden from buyers.");
    }

    public function activate(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        $product->update(['status' => 'active']);

        return back()->with('status', "\"{$product->name}\" is active again on the marketplace.");
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        if (! $product->canBeDeleted()) {
            return back()->withErrors([
                'product' => "Cannot delete \"{$product->name}\" because it is part of active uncompleted orders.",
            ]);
        }

        $name = $product->name;
        $product->delete();

        return redirect()->route('seller.products.index')
            ->with('status', "\"{$name}\" has been safely removed.");
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:activate,archive,delete'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $sellerId = $request->user()->id;
        $products = Product::where('seller_id', $sellerId)
            ->whereIn('id', $validated['product_ids'])
            ->get();

        $count = $products->count();

        match ($validated['action']) {
            'activate' => $products->each->update(['status' => 'active']),
            'archive' => $products->each->update(['status' => 'archived']),
            'delete' => $products->each(function (Product $p) {
                if ($p->canBeDeleted()) {
                    $p->delete();
                }
            }),
        };

        return back()->with('status', "Bulk action '{$validated['action']}' applied to {$count} products.");
    }

    private function validateProductData(Request $request, ?Product $product, bool $isDraft): array
    {
        $seller = $request->user();
        $allowedCategoryIds = $seller->getEffectiveSellerCategories()->pluck('id')->toArray();

        $rules = [
            'name' => [$isDraft ? 'nullable' : 'required', 'string', 'max:255'],
            'category_id' => [
                $isDraft ? 'nullable' : 'required',
                'exists:categories,id',
                Rule::in($allowedCategoryIds),
            ],
            'store_category_id' => ['nullable', 'exists:store_categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => [$isDraft ? 'nullable' : 'required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => [$isDraft ? 'nullable' : 'required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            'dimensions' => ['nullable', 'string', 'max:100'],
            'max_purchase_limit' => ['nullable', 'integer', 'min:1'],
            'image_url' => ['nullable', 'string'],
            'images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'colors' => ['nullable', 'string', 'max:500'],
            'sizes' => ['nullable', 'string', 'max:500'],
            'variants_json' => ['nullable', 'string'],
        ];

        return $request->validate($rules, [
            'category_id.in' => 'Your store is not configured to sell in this category. You can only select categories enabled for your shop in Store Settings.',
        ]);
    }

    private function prepareProductData(Request $request, array $validated, ?Product $product): array
    {
        $toList = fn (?string $raw) => $raw
            ? array_values(array_filter(array_map('trim', explode(',', $raw))))
            : [];

        // Gallery handling
        $gallery = $product ? ($product->gallery ?? []) : [];

        // If explicit image URL was entered and not in gallery, add it
        if (! empty($validated['image_url'])) {
            if (! in_array($validated['image_url'], $gallery, true)) {
                array_unshift($gallery, $validated['image_url']);
            }
        }

        // Uploaded files
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('products', 'public');
                $gallery[] = Storage::url($path);
            }
        }

        // Removed images list from form
        if ($request->has('remove_images')) {
            $toRemove = (array) $request->input('remove_images');
            $gallery = array_values(array_diff($gallery, $toRemove));
        }

        // Variants JSON matrix handling
        $variantsData = null;
        if ($request->has('variants_json')) {
            $raw = $request->input('variants_json');
            if (! empty($raw)) {
                $decoded = is_array($raw) ? $raw : json_decode($raw, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $variantsData = array_values(array_filter($decoded, fn ($v) => !empty($v['name']) || !empty($v['sku'])));
                }
            }
        } else {
            $variantsData = $product?->variants_data;
        }

        $stock = (int) ($validated['stock'] ?? 0);
        $price = (float) ($validated['price'] ?? 0);

        // If variants data is provided, sync total stock and price
        if ($variantsData && is_array($variantsData)) {
            $totalVariantStock = 0;
            $variantPrices = [];
            foreach ($variantsData as $v) {
                if (isset($v['stock'])) {
                    $totalVariantStock += (int) $v['stock'];
                }
                if (isset($v['price']) && (float) $v['price'] > 0) {
                    $variantPrices[] = (float) $v['price'];
                }
            }
            if ($totalVariantStock > 0 && ! $request->filled('stock')) {
                $stock = $totalVariantStock;
            }
            if (! empty($variantPrices) && ! $request->filled('price')) {
                $price = min($variantPrices);
            }
        }

        return [
            'name' => $validated['name'] ?? 'Untitled Draft Product',
            'category_id' => $validated['category_id'] ?? 1,
            'store_category_id' => $validated['store_category_id'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'description' => $validated['description'] ?? null,
            'price' => $price,
            'compare_price' => $validated['compare_price'] ?? null,
            'stock' => $stock,
            'low_stock_threshold' => (int) ($validated['low_stock_threshold'] ?? 10),
            'weight' => (float) ($validated['weight'] ?? 1.00),
            'dimensions' => $validated['dimensions'] ?? null,
            'max_purchase_limit' => $validated['max_purchase_limit'] ?? null,
            'gallery' => array_values($gallery),
            'colors' => $toList($validated['colors'] ?? null),
            'sizes' => $toList($validated['sizes'] ?? null),
            'variants_data' => $variantsData,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-" . $i++;
        }

        return $slug;
    }

    private function authorizeOwner(Request $request, Product $product): void
    {
        abort_unless($product->seller_id === $request->user()->id, 403);
    }
}

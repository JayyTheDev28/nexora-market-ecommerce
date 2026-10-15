<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = $request->user()->products()
            ->with('category')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->latest()
            ->get();

        $counts = [
            'all' => $request->user()->products()->count(),
            'active' => $request->user()->products()->where('status', 'active')->count(),
            'draft' => $request->user()->products()->where('status', 'draft')->count(),
            'archived' => $request->user()->products()->where('status', 'archived')->count(),
            'low_stock' => $request->user()->products()->where('status', 'active')->where('stock', '<=', 10)->count(),
        ];

        return view('seller.products.index', compact('products', 'counts'));
    }

    public function create(): View
    {
        $categories = Category::orderBy('name')->get();

        return view('seller.products.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        $product = $request->user()->products()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
            'status' => $request->boolean('publish') ? 'active' : 'draft',
        ]);

        return redirect()->route('seller.products.index')
            ->with('status', "\"{$product->name}\" was added to your inventory.");
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeOwner($request, $product);

        $categories = Category::orderBy('name')->get();

        return view('seller.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        $validated = $this->validateProduct($request, $product);

        $product->update($validated);

        return redirect()->route('seller.products.index')
            ->with('status', "\"{$product->name}\" was updated.");
    }

    public function archive(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        $product->update(['status' => 'archived']);

        return back()->with('status', "\"{$product->name}\" was archived and is no longer visible to buyers.");
    }

    public function activate(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwner($request, $product);

        $product->update(['status' => 'active']);

        return back()->with('status', "\"{$product->name}\" is active again.");
    }

    private function validateProduct(Request $request, ?Product $ignore = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'gt:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url'],
            'colors' => ['nullable', 'string', 'max:500'],
            'sizes' => ['nullable', 'string', 'max:500'],
        ]);

        // Comma-separated input ("Red, Blue, Green") -> clean array, matching
        // the shape colors/sizes were already stored in from ProductSeeder.
        $toList = fn (?string $raw) => $raw
            ? array_values(array_filter(array_map('trim', explode(',', $raw))))
            : [];

        return [
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'compare_price' => $validated['compare_price'] ?? null,
            'stock' => $validated['stock'],
            'gallery' => $validated['image_url'] ? [$validated['image_url']] : ($ignore->gallery ?? []),
            'colors' => $toList($validated['colors'] ?? null),
            'sizes' => $toList($validated['sizes'] ?? null),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-" . $i++;
        }

        return $slug;
    }

    private function authorizeOwner(Request $request, Product $product): void
    {
        abort_if($product->seller_id !== $request->user()->id, 403);
    }
}

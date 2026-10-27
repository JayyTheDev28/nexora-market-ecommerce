<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $categories = StoreCategory::where('seller_id', $sellerId)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('products')->orderBy('order_index')])
            ->withCount('products')
            ->orderBy('order_index')
            ->get();

        $allCategories = StoreCategory::where('seller_id', $sellerId)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('seller.categories.index', compact('categories', 'allCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $sellerId = $request->user()->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => [
                'nullable',
                Rule::exists('store_categories', 'id')->where(fn ($q) => $q->where('seller_id', $sellerId)->whereNull('parent_id')),
            ],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($validated['name']);
        $uniqueSlug = $slug;
        $i = 1;
        while (StoreCategory::where('seller_id', $sellerId)->where('slug', $uniqueSlug)->exists()) {
            $uniqueSlug = "{$slug}-" . $i++;
        }

        $highestOrder = StoreCategory::where('seller_id', $sellerId)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order_index') ?? 0;

        StoreCategory::create([
            'seller_id' => $sellerId,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $uniqueSlug,
            'order_index' => $highestOrder + 1,
            'is_visible' => $request->boolean('is_visible', true),
        ]);

        return redirect()->route('seller.categories.index')
            ->with('status', "Store category '{$validated['name']}' was created successfully.");
    }

    public function update(Request $request, StoreCategory $category): RedirectResponse
    {
        $this->authorizeOwner($request, $category);

        $sellerId = $request->user()->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => [
                'nullable',
                Rule::notIn([$category->id]),
                Rule::exists('store_categories', 'id')->where(fn ($q) => $q->where('seller_id', $sellerId)->whereNull('parent_id')),
            ],
            'is_visible' => ['nullable', 'boolean'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $updates = [
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_visible' => $request->boolean('is_visible', true),
        ];

        if (isset($validated['order_index'])) {
            $updates['order_index'] = (int) $validated['order_index'];
        }

        $category->update($updates);

        return redirect()->route('seller.categories.index')
            ->with('status', "Category '{$category->name}' was updated.");
    }

    public function toggleVisibility(Request $request, StoreCategory $category): RedirectResponse
    {
        $this->authorizeOwner($request, $category);

        $category->update(['is_visible' => ! $category->is_visible]);

        $state = $category->is_visible ? 'visible' : 'hidden';
        return redirect()->route('seller.categories.index')
            ->with('status', "Category '{$category->name}' is now {$state}.");
    }

    public function destroy(Request $request, StoreCategory $category): RedirectResponse
    {
        $this->authorizeOwner($request, $category);

        $name = $category->name;

        // Unlink products assigned to this category and any child categories
        Product::where('store_category_id', $category->id)->update(['store_category_id' => null]);
        foreach ($category->children as $child) {
            Product::where('store_category_id', $child->id)->update(['store_category_id' => null]);
            $child->delete();
        }

        $category->delete();

        return redirect()->route('seller.categories.index')
            ->with('status', "Category '{$name}' was removed. Products were safely unlinked.");
    }

    private function authorizeOwner(Request $request, StoreCategory $category): void
    {
        abort_unless($category->seller_id === $request->user()->id, 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * B3: Browse, Search & Filter
     * - Exact/partial keyword search (case-insensitive, trimmed)
     * - Price range filters (min_price, max_price)
     * - Platform & store category browsing
     * - Multi-attribute sorting (price_asc, price_desc, latest, sales, rating)
     * - Soft-delete & inactive product exclusion
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->where('status', 'active')
            ->whereHas('seller', fn ($q) => $q->where('account_status', 'active'))
            ->with(['category', 'seller.sellerProfile', 'reviews']);

        // Trimmed keyword search (exact & partial, case-insensitive)
        $rawQ = $request->query('q', '');
        $keyword = is_string($rawQ) ? trim($rawQ) : '';
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%");
            });
        }

        // Category filter (slug or id)
        $categorySlug = $request->query('category', '');
        if ($categorySlug !== '') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug)->orWhere('id', $categorySlug));
        }

        // Store / Seller filter
        $sellerId = $request->query('seller_id');
        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        // Price range filters
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        // Multi-attribute sorting
        $sortBy = $request->query('sort', 'latest');
        match ($sortBy) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'sales' => $query->withCount('orderItems')->orderByDesc('order_items_count'),
            'rating' => $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating'),
            default => $query->latest('id'),
        };

        $products = $query->get()->map(function (Product $p) {
            $effectivePrice = $p->effective_price;
            return [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category->slug ?? '',
                'categoryName' => $p->category->name ?? '',
                'seller' => $p->seller->sellerProfile->business_name ?? $p->seller->full_name,
                'sellerId' => $p->seller_id,
                'price' => $effectivePrice,
                'regularPrice' => (float) $p->price,
                'comparePrice' => $p->compare_price ? (float) $p->compare_price : null,
                'isFlashSale' => $p->isFlashSaleActive(),
                'stock' => $p->stock,
                'weight' => (float) ($p->weight ?? 1.0),
                'rating' => $p->averageRating(),
                'image' => $p->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($p->name),
                'url' => url("/products/{$p->id}"),
            ];
        })->values()->all();

        $categories = Category::orderBy('name')->get(['id', 'slug', 'name', 'icon'])->toArray();

        $initialQuery = $keyword;
        $initialCategory = $categorySlug;
        $initialSort = $sortBy;

        return view('buyer.catalog.index', compact(
            'products',
            'categories',
            'initialQuery',
            'initialCategory',
            'initialSort'
        ));
    }
}

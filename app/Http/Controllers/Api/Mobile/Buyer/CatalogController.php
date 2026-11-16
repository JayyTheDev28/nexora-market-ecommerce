<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\CategoryResource;
use App\Http\Resources\Api\Mobile\ProductResource;
use App\Http\Resources\Api\Mobile\StoreResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Buyer Home curated database feed (categories, real active flash sales only,
     * recommended, new arrivals, featured stores — zero fabricated promos/products).
     * GET /api/mobile/v1/buyer/home
     */
    public function home(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();

        $baseQuery = fn () => Product::query()
            ->where('status', 'active')
            ->whereHas('seller', fn ($q) => $q->where('account_status', 'active'))
            ->with(['category', 'seller.sellerProfile', 'seller.defaultAddress', 'seller.address', 'reviews']);

        $flashSales = $baseQuery()
            ->whereNotNull('flash_sale_price')
            ->where('flash_sale_price', '>', 0)
            ->whereColumn('flash_sale_price', '<', 'price')
            ->where(function ($q) {
                $q->whereNull('flash_sale_ends_at')->orWhere('flash_sale_ends_at', '>', now());
            })
            ->where('stock', '>', 0)
            ->latest('id')
            ->limit(10)
            ->get();

        $newArrivals = $baseQuery()
            ->latest('id')
            ->limit(12)
            ->get();

        $recommended = $baseQuery()
            ->withCount('orderItems')
            ->orderByDesc('order_items_count')
            ->latest('id')
            ->limit(12)
            ->get();

        $featuredStores = User::query()
            ->where('role', 'seller')
            ->where('account_status', 'active')
            ->where('approval_status', 'approved')
            ->whereHas('products', fn ($q) => $q->where('status', 'active'))
            ->whereHas('sellerProfile', fn ($q) => $q->where(function ($sq) {
                $sq->whereNull('is_vacation_mode')->orWhere('is_vacation_mode', false);
            }))
            ->with(['sellerProfile', 'storeCategories'])
            ->withCount(['products as active_products_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('active_products_count')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => [
                'categories' => CategoryResource::collection($categories),
                'flash_sales' => ProductResource::collection($flashSales),
                'has_active_flash_sales' => $flashSales->isNotEmpty(),
                'recommended' => ProductResource::collection($recommended),
                'new_arrivals' => ProductResource::collection($newArrivals),
                'featured_stores' => StoreResource::collection($featuredStores),
            ],
            'message' => 'Buyer home feed retrieved successfully.',
        ]);
    }

    /**
     * B3: Browse, Search & Filter Catalog
     * GET /api/mobile/v1/buyer/catalog
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('status', 'active')
            ->whereHas('seller', fn ($q) => $q->where('account_status', 'active'))
            ->with(['category', 'seller.sellerProfile', 'seller.defaultAddress', 'seller.address', 'reviews']);

        $rawQ = $request->query('q', '');
        $keyword = is_string($rawQ) ? trim($rawQ) : '';
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%");
            });
        }

        $categorySlug = $request->query('category', '');
        if ($categorySlug !== '') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug)->orWhere('id', $categorySlug));
        }

        $sellerId = $request->query('seller_id');
        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        if ($request->boolean('flash_sale')) {
            $query->whereNotNull('flash_sale_price')
                ->where('flash_sale_price', '>', 0)
                ->whereColumn('flash_sale_price', '<', 'price')
                ->where(function ($q) {
                    $q->whereNull('flash_sale_ends_at')->orWhere('flash_sale_ends_at', '>', now());
                });
        }

        if ($request->filled('min_rating')) {
            $minRating = (float) $request->query('min_rating');
            if ($minRating > 0) {
                $query->whereHas('reviews')
                    ->withAvg('reviews', 'rating')
                    ->having('reviews_avg_rating', '>=', $minRating);
            }
        }

        $rawLocation = $request->query('location', '');
        $location = is_string($rawLocation) ? trim($rawLocation) : '';
        if ($location !== '') {
            $query->whereHas('seller', function ($sq) use ($location) {
                $sq->whereHas('defaultAddress', fn ($aq) => $aq->where('municipality_name', 'LIKE', "%{$location}%")->orWhere('province_name', 'LIKE', "%{$location}%"))
                   ->orWhereHas('address', fn ($aq) => $aq->where('municipality_name', 'LIKE', "%{$location}%")->orWhere('province_name', 'LIKE', "%{$location}%"));
            });
        }

        $sortBy = $request->query('sort', 'latest');
        match ($sortBy) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'sales' => $query->withCount('orderItems')->orderByDesc('order_items_count'),
            'rating' => $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating'),
            default => $query->latest('id'),
        };

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginated = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => ProductResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'message' => 'Catalog retrieved successfully.',
        ]);
    }

    /**
     * Categories list
     * GET /api/mobile/v1/buyer/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),
            'message' => 'Categories retrieved successfully.',
        ]);
    }
}


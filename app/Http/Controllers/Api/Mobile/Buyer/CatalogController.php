<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\CategoryResource;
use App\Http\Resources\Api\Mobile\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
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


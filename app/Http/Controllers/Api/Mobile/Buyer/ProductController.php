<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\ProductDetailResource;
use App\Http\Resources\Api\Mobile\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * B4: Product Detail
     * GET /api/mobile/v1/buyer/products/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $product = Product::with([
            'category',
            'seller.sellerProfile',
            'seller.defaultAddress',
            'seller.address',
            'reviews.buyer',
        ])->findOrFail($id);

        if ($product->trashed() || $product->status !== 'active') {
            return response()->json([
                'message' => 'Product is no longer available.',
            ], 404);
        }

        // Related products
        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->with(['seller.sellerProfile', 'seller.defaultAddress', 'seller.address', 'category'])
            ->limit(4)
            ->get();

        return response()->json([
            'data' => [
                'product' => new ProductDetailResource($product),
                'related_products' => ProductResource::collection($related),
            ],
            'message' => 'Product details retrieved successfully.',
        ]);
    }

    /**
     * Paginated reviews & real database star rating breakdown for a product.
     * GET /api/mobile/v1/buyer/products/{id}/reviews
     */
    public function reviews(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        if ($product->trashed() || $product->status !== 'active') {
            return response()->json(['message' => 'Product is no longer available.'], 404);
        }

        $star = (int) $request->query('rating', 0);
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $totalReviews = $product->reviews()->count();
        $countsByStar = $product->reviews()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        $breakdown = [];
        foreach ([5, 4, 3, 2, 1] as $s) {
            $count = (int) ($countsByStar[$s] ?? 0);
            $breakdown[(string) $s] = [
                'count' => $count,
                'ratio' => $totalReviews > 0 ? round($count / $totalReviews, 4) : 0.0,
            ];
        }

        $query = $product->reviews()->with('buyer')->latest();
        if ($star >= 1 && $star <= 5) {
            $query->where('rating', $star);
        }
        $paginated = $query->paginate($perPage)->withQueryString();

        $reviews = collect($paginated->items())->map(function ($r) {
            $photos = array_values(array_filter(array_map(function ($p) {
                if (empty($p)) {
                    return null;
                }
                if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
                    return $p;
                }
                return url(\Illuminate\Support\Facades\Storage::url($p));
            }, $r->photos ?? [])));

            return [
                'id' => $r->id,
                'buyer_name' => $r->buyer?->full_name ?? 'Verified Buyer',
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'photos' => $photos,
                'created_at' => $r->created_at?->diffForHumans(),
                'created_at_iso' => $r->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'average_rating' => $totalReviews > 0 ? round((float) $product->averageRating(), 2) : 0.0,
                'reviews_count' => $totalReviews,
                'breakdown' => $breakdown,
                'reviews' => $reviews,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
            'message' => 'Product reviews retrieved successfully.',
        ]);
    }
}


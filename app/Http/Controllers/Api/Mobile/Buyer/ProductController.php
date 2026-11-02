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
            ->with(['seller.sellerProfile', 'category'])
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
}


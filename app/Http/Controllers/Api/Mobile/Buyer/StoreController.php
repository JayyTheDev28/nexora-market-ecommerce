<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\ProductResource;
use App\Http\Resources\Api\Mobile\StoreResource;
use App\Http\Resources\Api\Mobile\VoucherResource;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Store Profile and Catalog
     * GET /api/mobile/v1/buyer/stores/{seller}
     */
    public function show(Request $request, User $seller): JsonResponse
    {
        if ($seller->role !== 'seller' || $seller->approval_status !== 'approved' || $seller->account_status !== 'active') {
            return response()->json([
                'message' => 'This store is not available.',
            ], 404);
        }

        $productsQuery = Product::where('seller_id', $seller->id)
            ->where('status', 'active')
            ->with(['category', 'seller.sellerProfile']);

        if ($catId = $request->query('store_category')) {
            $productsQuery->where('store_category_id', $catId);
        }

        if ($search = $request->query('q')) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'price_low' => $productsQuery->orderBy('price', 'asc'),
            'price_high' => $productsQuery->orderBy('price', 'desc'),
            'name' => $productsQuery->orderBy('name', 'asc'),
            default => $productsQuery->latest(),
        };

        $perPage = min(50, max(1, (int) $request->query('per_page', 16)));
        $products = $productsQuery->paginate($perPage)->withQueryString();

        $storeVouchers = Voucher::where('seller_id', $seller->id)
            ->where('voucher_scope', 'store')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        return response()->json([
            'data' => [
                'store' => new StoreResource($seller),
                'products' => ProductResource::collection($products->items()),
                'vouchers' => VoucherResource::collection($storeVouchers),
            ],
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
            'message' => 'Store details retrieved successfully.',
        ]);
    }
}


<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\ProductResource;
use App\Http\Resources\Api\Mobile\StoreResource;
use App\Models\Product;
use App\Models\StoreFollow;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * B5: Wishlist list
     * GET /api/mobile/v1/buyer/wishlist
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $wishlists = Wishlist::where('user_id', $user->id)
            ->with(['product' => fn ($q) => $q->withTrashed()->with(['seller.sellerProfile', 'category'])])
            ->latest()
            ->get();

        $products = $wishlists->map(fn ($w) => $w->product)->filter();

        return response()->json([
            'data' => ProductResource::collection($products),
            'message' => 'Wishlist retrieved successfully.',
        ]);
    }

    /**
     * B5: Toggle Wishlist
     * POST /api/mobile/v1/buyer/wishlist/{product}/toggle
     */
    public function toggle(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();
        $product = Product::withTrashed()->findOrFail($productId);

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
            $message = 'Removed from your wishlist.';
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
            $inWishlist = true;
            $message = 'Added to your wishlist.';
        }

        return response()->json([
            'data' => [
                'in_wishlist' => $inWishlist,
                'count' => $user->wishlists()->count(),
            ],
            'message' => $message,
        ]);
    }

    /**
     * B5: Followed stores list
     * GET /api/mobile/v1/buyer/stores/followed
     */
    public function followedStores(Request $request): JsonResponse
    {
        $user = $request->user();
        $followed = $user->followedStores()->with('sellerProfile')->get();

        return response()->json([
            'data' => StoreResource::collection($followed),
            'message' => 'Followed stores retrieved successfully.',
        ]);
    }

    /**
     * B5: Toggle store follow
     * POST /api/mobile/v1/buyer/stores/{seller}/follow-toggle
     */
    public function toggleFollow(Request $request, int $sellerId): JsonResponse
    {
        $user = $request->user();
        $seller = User::where('role', 'seller')->findOrFail($sellerId);

        $existing = StoreFollow::where('user_id', $user->id)
            ->where('seller_id', $seller->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $following = false;
            $message = 'Unfollowed ' . ($seller->sellerProfile?->business_name ?? $seller->full_name);
        } else {
            StoreFollow::create([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
            ]);
            $following = true;
            $message = 'Now following ' . ($seller->sellerProfile?->business_name ?? $seller->full_name);
        }

        return response()->json([
            'data' => [
                'is_following' => $following,
                'following' => $following,
                'followers_count' => StoreFollow::where('seller_id', $seller->id)->count(),
            ],
            'message' => $message,
        ]);
    }
}


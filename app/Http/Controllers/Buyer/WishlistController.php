<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreFollow;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    /**
     * B5: Wishlist management (handling sold-out/deleted items gracefully)
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $wishlists = Wishlist::where('user_id', $user->id)
            ->with(['product' => fn ($q) => $q->withTrashed()->with(['seller.sellerProfile', 'category'])])
            ->latest()
            ->get();

        $followedStores = $user->followedStores()->with('sellerProfile')->get();

        return view('buyer.wishlist.index', compact('wishlists', 'followedStores'));
    }

    /**
     * B5: Toggle Wishlist status for a product
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
            'success' => true,
            'inWishlist' => $inWishlist,
            'message' => $message,
            'count' => $user->wishlists()->count(),
        ]);
    }

    /**
     * B5: Toggle store follow
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
            $message = 'Unfollowed ' . ($seller->sellerProfile->business_name ?? $seller->full_name);
        } else {
            StoreFollow::create([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
            ]);
            $following = true;
            $message = 'Now following ' . ($seller->sellerProfile->business_name ?? $seller->full_name);
        }

        return response()->json([
            'success' => true,
            'following' => $following,
            'message' => $message,
            'followersCount' => StoreFollow::where('seller_id', $seller->id)->count(),
        ]);
    }
}

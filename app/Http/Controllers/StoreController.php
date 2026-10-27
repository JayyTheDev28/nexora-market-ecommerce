<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\StoreFollow;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    /**
     * Public store profile & catalog page for buyers
     */
    public function show(Request $request, User $seller): View
    {
        // Guard: ensure target user is an approved and active seller
        abort_unless(
            $seller->role === 'seller' &&
            $seller->approval_status === 'approved' &&
            $seller->account_status === 'active',
            404,
            'This store is not available.'
        );

        $profile = $seller->sellerProfile;
        $user = $request->user();

        $isFollowing = false;
        $claimedVoucherIds = [];
        if ($user) {
            $isFollowing = StoreFollow::where('user_id', $user->id)->where('seller_id', $seller->id)->exists();
            $claimedVoucherIds = $user->claimedVouchers()->pluck('voucher_id')->all();
        }

        $followersCount = StoreFollow::where('seller_id', $seller->id)->count();

        // Visible store-specific categories
        $storeCategories = StoreCategory::where('seller_id', $seller->id)
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_visible', true)])
            ->orderBy('order_index')
            ->get();

        // Query active products
        $productsQuery = Product::where('seller_id', $seller->id)
            ->where('status', 'active');

        // Filter by store category if selected
        if ($catId = $request->query('store_category')) {
            $productsQuery->where('store_category_id', $catId);
        }

        // Search query
        if ($search = $request->query('q')) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'price_low' => $productsQuery->orderBy('price', 'asc'),
            'price_high' => $productsQuery->orderBy('price', 'desc'),
            'name' => $productsQuery->orderBy('name', 'asc'),
            default => $productsQuery->latest(),
        };

        $products = $productsQuery->paginate(16)->withQueryString();

        // Active store vouchers for public claiming
        $storeVouchers = Voucher::where('seller_id', $seller->id)
            ->where('voucher_scope', 'store')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        // Store ratings aggregate
        $totalReviews = $seller->products()->withCount('reviews')->get()->sum('reviews_count');
        $averageRating = 5.0;
        $ratedProducts = $seller->products()->whereHas('reviews')->with('reviews')->get();
        if ($ratedProducts->isNotEmpty()) {
            $allReviews = $ratedProducts->flatMap->reviews;
            if ($allReviews->isNotEmpty()) {
                $averageRating = round($allReviews->avg('rating'), 1);
            }
        }

        return view('stores.show', compact(
            'seller',
            'profile',
            'isFollowing',
            'followersCount',
            'storeCategories',
            'products',
            'storeVouchers',
            'claimedVoucherIds',
            'totalReviews',
            'averageRating'
        ));
    }
}

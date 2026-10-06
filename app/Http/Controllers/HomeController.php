<?php

namespace App\Http\Controllers;

use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $heroImage = 'https://placehold.co/1200x1200/e5eeff/0058be?text=Nexora';

        $heroFeaturedProduct = [
            'name' => 'Minimalist Chair',
            'price' => 349.00,
            'seller' => 'Nexus Home',
            'image' => 'https://placehold.co/200x200/f8f9ff/0058be?text=Chair',
        ];

        $heroReviewerAvatars = [
            'https://placehold.co/80x80/d9dff5/121c28?text=A',
            'https://placehold.co/80x80/d9dff5/121c28?text=B',
            'https://placehold.co/80x80/d9dff5/121c28?text=C',
        ];

        $featuredSellers = [
            ['name' => 'Nexus Home', 'rating' => 4.9, 'productCount' => 250, 'logo' => 'https://placehold.co/200x200/eef4ff/0058be?text=NH', 'url' => '#'],
            ['name' => 'Tech Haven', 'rating' => 4.8, 'productCount' => 120, 'logo' => 'https://placehold.co/200x200/eef4ff/0058be?text=TH', 'url' => '#'],
            ['name' => 'Luxe Goods Co.', 'rating' => 5.0, 'productCount' => 85, 'logo' => 'https://placehold.co/200x200/eef4ff/0058be?text=LG', 'url' => '#'],
        ];

        $categories = [
            ['name' => 'Electronics', 'icon' => 'devices', 'iconBg' => 'bg-primary-fixed-dim/30', 'iconColor' => 'text-primary', 'url' => url('/catalog?category=electronics')],
            ['name' => 'Fashion', 'icon' => 'checkroom', 'iconBg' => 'bg-tertiary-fixed-dim/30', 'iconColor' => 'text-tertiary', 'url' => url('/catalog?category=fashion')],
            ['name' => 'Home & Living', 'icon' => 'chair', 'iconBg' => 'bg-secondary-fixed/50', 'iconColor' => 'text-secondary', 'url' => url('/catalog?category=home-living')],
            ['name' => 'Beauty', 'icon' => 'face_retouching_natural', 'iconBg' => 'bg-error-container/50', 'iconColor' => 'text-error', 'url' => url('/catalog?category=beauty')],
            ['name' => 'Sports', 'icon' => 'sports_soccer', 'iconBg' => 'bg-primary-container/20', 'iconColor' => 'text-primary', 'url' => url('/catalog?category=sports')],
            ['name' => 'Gaming', 'icon' => 'sports_esports', 'iconBg' => 'bg-inverse-primary/30', 'iconColor' => 'text-on-surface', 'url' => url('/catalog?category=gaming')],
            ['name' => 'Accessories', 'icon' => 'watch', 'iconBg' => 'bg-surface-variant', 'iconColor' => 'text-on-surface-variant', 'url' => url('/catalog?category=accessories')],
            ['name' => 'Kids', 'icon' => 'child_care', 'iconBg' => 'bg-tertiary-fixed/50', 'iconColor' => 'text-on-tertiary-fixed', 'url' => url('/catalog?category=kids')],
        ];

        $promo = [
            'tag' => 'Limited Time Offer',
            'headline' => 'Season of Tech:<br> Up to 40% Off',
            'description' => "Upgrade your setup with the latest gadgets from top verified sellers. Don't miss out on these exclusive deals.",
            'cta' => 'Grab the Deal',
            'url' => url('/catalog?category=electronics'),
            'image' => 'https://placehold.co/1200x800/27313e/adc6ff?text=Season+of+Tech',
        ];

        // New Arrivals / Trending now pull from the real products table
        // (seeded by ProductSeeder from the same data SampleCatalog used to
        // provide statically), so IDs/prices/"Add to Cart" stay consistent
        // with /catalog and /products/{id}.
        $newArrivals = Product::where('status', 'active')
            ->with('seller.sellerProfile')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => $this->toCard($p))
            ->values()
            ->all();

        $trendingProducts = Product::where('status', 'active')
            ->with('seller.sellerProfile')
            ->inRandomOrder()
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => $this->toCard($p))
            ->values()
            ->all();

        $reviews = [
            ['name' => 'Sarah J.', 'rating' => 5, 'quote' => 'Amazing selection and fast shipping. I found exactly what I was looking for and the quality is outstanding.', 'avatar' => 'https://placehold.co/100x100/d9dff5/121c28?text=SJ'],
            ['name' => 'Michael T.', 'rating' => 5, 'quote' => "The best online shopping experience I've had. The curated collections make it so easy to find great gifts.", 'avatar' => 'https://placehold.co/100x100/d9dff5/121c28?text=MT'],
            ['name' => 'Emily R.', 'rating' => 4.5, 'quote' => 'Customer service is top-notch and the platform is incredibly easy to use. Highly recommend to everyone.', 'avatar' => 'https://placehold.co/100x100/d9dff5/121c28?text=ER'],
        ];

        return view('home.index', compact(
            'heroImage',
            'heroFeaturedProduct',
            'heroReviewerAvatars',
            'featuredSellers',
            'categories',
            'promo',
            'newArrivals',
            'trendingProducts',
            'reviews'
        ));
    }

    /**
     * Map a real Product model into the shape <x-product-card> expects.
     */
    private function toCard(Product $p): array
    {
        return [
            'id' => $p->id,
            'seller' => $p->seller->sellerProfile->business_name ?? $p->seller->full_name,
            'name' => $p->name,
            'price' => (float) $p->price,
            'comparePrice' => $p->compare_price ? (float) $p->compare_price : null,
            'rating' => null,
            'reviewCount' => null,
            'badge' => null,
            'image' => $p->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($p->name),
            'url' => url("/products/{$p->id}"),
        ];
    }
}


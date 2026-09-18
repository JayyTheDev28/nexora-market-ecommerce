<?php

namespace App\Support;

/**
 * Static sample catalog data shared across the storefront (catalog listing,
 * product detail page). This stands in for the Product/Category tables
 * until the database phase — swap SampleCatalog::products() etc. for real
 * Eloquent queries later without needing to change the Blade views much,
 * since they already consume plain arrays/objects.
 */
class SampleCatalog
{
    public static function categories(): array
    {
        return [
            ['slug' => 'electronics', 'name' => 'Electronics', 'icon' => 'devices'],
            ['slug' => 'fashion', 'name' => 'Fashion', 'icon' => 'checkroom'],
            ['slug' => 'home-living', 'name' => 'Home & Living', 'icon' => 'chair'],
            ['slug' => 'beauty', 'name' => 'Beauty', 'icon' => 'face_retouching_natural'],
            ['slug' => 'sports', 'name' => 'Sports', 'icon' => 'sports_soccer'],
            ['slug' => 'gaming', 'name' => 'Gaming', 'icon' => 'sports_esports'],
            ['slug' => 'accessories', 'name' => 'Accessories', 'icon' => 'watch'],
            ['slug' => 'kids', 'name' => 'Kids', 'icon' => 'child_care'],
        ];
    }

    public static function products(): array
    {
        return [
            [
                'id' => 1,
                'slug' => 'aeromax-elite-running-shoe',
                'name' => 'AeroMax Elite Running Shoe',
                'category' => 'sports',
                'seller' => 'Premium Sports',
                'price' => 1400.00,
                'comparePrice' => null,
                'rating' => 4.9,
                'reviewCount' => 340,
                'stock' => 18,
                'badge' => 'new',
                'image' => 'https://placehold.co/600x600/e5eeff/0058be?text=Shoe',
                'gallery' => [
                    'https://placehold.co/800x800/e5eeff/0058be?text=Shoe+1',
                    'https://placehold.co/800x800/eef4ff/0058be?text=Shoe+2',
                    'https://placehold.co/800x800/dfe9fa/0058be?text=Shoe+3',
                ],
                'colors' => ['Midnight Black', 'Cloud White', 'Volt Green'],
                'sizes' => ['US 7', 'US 8', 'US 9', 'US 10', 'US 11'],
                'description' => 'A lightweight daily trainer with responsive cushioning and a breathable knit upper — built for everything from morning runs to all-day wear.',
            ],
            [
                'id' => 2,
                'slug' => 'artisan-ceramic-pour-over-set',
                'name' => 'Artisan Ceramic Pour-Over Set',
                'category' => 'home-living',
                'seller' => 'Echo Home',
                'price' => 650.00,
                'comparePrice' => null,
                'rating' => 4.8,
                'reviewCount' => 128,
                'stock' => 32,
                'badge' => 'new',
                'image' => 'https://placehold.co/600x600/e5eeff/0058be?text=Ceramic',
                'gallery' => [
                    'https://placehold.co/800x800/e5eeff/0058be?text=Ceramic+1',
                    'https://placehold.co/800x800/eef4ff/0058be?text=Ceramic+2',
                ],
                'colors' => ['Terracotta', 'Sand', 'Charcoal'],
                'sizes' => [],
                'description' => 'Hand-glazed stoneware pour-over dripper and mug set, designed to slow down your morning coffee ritual.',
            ],
            [
                'id' => 3,
                'slug' => 'nimbus-mechanical-keyboard',
                'name' => 'Nimbus Mechanical Keyboard',
                'category' => 'electronics',
                'seller' => 'Tech Haven',
                'price' => 1299.00,
                'comparePrice' => 1590.00,
                'rating' => 4.7,
                'reviewCount' => 210,
                'stock' => 9,
                'badge' => 'sale',
                'image' => 'https://placehold.co/600x600/e5eeff/0058be?text=Keyboard',
                'gallery' => [
                    'https://placehold.co/800x800/e5eeff/0058be?text=Keyboard+1',
                    'https://placehold.co/800x800/eef4ff/0058be?text=Keyboard+2',
                    'https://placehold.co/800x800/dfe9fa/0058be?text=Keyboard+3',
                ],
                'colors' => ['Graphite', 'Pearl White'],
                'sizes' => ['65%', 'TKL', 'Full Size'],
                'description' => 'Hot-swappable mechanical keyboard with a tactile switch profile, per-key RGB, and a CNC-machined aluminum frame.',
            ],
            [
                'id' => 4,
                'slug' => 'classic-leather-tote',
                'name' => 'Classic Leather Tote',
                'category' => 'fashion',
                'seller' => 'Luxe Goods Co.',
                'price' => 2150.00,
                'comparePrice' => null,
                'rating' => 5.0,
                'reviewCount' => 42,
                'stock' => 6,
                'badge' => 'new',
                'image' => 'https://placehold.co/600x600/eef4ff/0058be?text=Tote',
                'gallery' => [
                    'https://placehold.co/800x800/eef4ff/0058be?text=Tote+1',
                    'https://placehold.co/800x800/e5eeff/0058be?text=Tote+2',
                ],
                'colors' => ['Cognac', 'Black', 'Taupe'],
                'sizes' => [],
                'description' => 'Full-grain leather tote with a structured base, interior zip pocket, and hardware that ages beautifully over time.',
            ],
            [
                'id' => 5,
                'slug' => 'lumen-desk-lamp',
                'name' => 'Lumen Adjustable Desk Lamp',
                'category' => 'home-living',
                'seller' => 'Echo Home',
                'price' => 890.00,
                'comparePrice' => null,
                'rating' => 4.6,
                'reviewCount' => 76,
                'stock' => 21,
                'badge' => null,
                'image' => 'https://placehold.co/600x600/dfe9fa/0058be?text=Lamp',
                'gallery' => [
                    'https://placehold.co/800x800/dfe9fa/0058be?text=Lamp+1',
                    'https://placehold.co/800x800/eef4ff/0058be?text=Lamp+2',
                ],
                'colors' => ['Matte Black', 'Warm Brass'],
                'sizes' => [],
                'description' => 'Three-axis adjustable desk lamp with stepless dimming and a warm-to-cool color temperature range.',
            ],
            [
                'id' => 6,
                'slug' => 'voyager-wireless-earbuds',
                'name' => 'Voyager Wireless Earbuds',
                'category' => 'electronics',
                'seller' => 'Tech Haven',
                'price' => 1850.00,
                'comparePrice' => 2200.00,
                'rating' => 4.5,
                'reviewCount' => 501,
                'stock' => 40,
                'badge' => 'sale',
                'image' => 'https://placehold.co/600x600/e5eeff/0058be?text=Earbuds',
                'gallery' => [
                    'https://placehold.co/800x800/e5eeff/0058be?text=Earbuds+1',
                    'https://placehold.co/800x800/eef4ff/0058be?text=Earbuds+2',
                    'https://placehold.co/800x800/dfe9fa/0058be?text=Earbuds+3',
                ],
                'colors' => ['Onyx', 'Pearl'],
                'sizes' => [],
                'description' => 'Active noise-cancelling earbuds with a 32-hour case battery, wireless charging, and a low-latency gaming mode.',
            ],
            [
                'id' => 7,
                'slug' => 'strata-yoga-mat',
                'name' => 'Strata Non-Slip Yoga Mat',
                'category' => 'sports',
                'seller' => 'Premium Sports',
                'price' => 780.00,
                'comparePrice' => null,
                'rating' => 4.8,
                'reviewCount' => 164,
                'stock' => 50,
                'badge' => null,
                'image' => 'https://placehold.co/600x600/eef4ff/0058be?text=Yoga+Mat',
                'gallery' => [
                    'https://placehold.co/800x800/eef4ff/0058be?text=Mat+1',
                    'https://placehold.co/800x800/e5eeff/0058be?text=Mat+2',
                ],
                'colors' => ['Sage', 'Slate', 'Blush'],
                'sizes' => ['4mm', '6mm'],
                'description' => 'Double-sided non-slip mat with natural rubber base and moisture-wicking top layer for hot yoga sessions.',
            ],
            [
                'id' => 8,
                'slug' => 'glow-serum-set',
                'name' => 'Glow Vitamin C Serum Set',
                'category' => 'beauty',
                'seller' => 'Luxe Goods Co.',
                'price' => 1120.00,
                'comparePrice' => 1350.00,
                'rating' => 4.7,
                'reviewCount' => 289,
                'stock' => 27,
                'badge' => 'sale',
                'image' => 'https://placehold.co/600x600/ffdad6/924700?text=Serum',
                'gallery' => [
                    'https://placehold.co/800x800/ffdad6/924700?text=Serum+1',
                    'https://placehold.co/800x800/eef4ff/924700?text=Serum+2',
                ],
                'colors' => [],
                'sizes' => ['30ml', '50ml'],
                'description' => 'Brightening vitamin C serum trio formulated for daily use — fades dark spots and evens out skin tone over time.',
            ],
        ];
    }

    public static function find(int $id): ?array
    {
        foreach (self::products() as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }
        return null;
    }

    public static function relatedTo(array $product, int $limit = 4): array
    {
        $related = array_values(array_filter(
            self::products(),
            fn ($p) => $p['category'] === $product['category'] && $p['id'] !== $product['id']
        ));
        return array_slice($related, 0, $limit);
    }
}

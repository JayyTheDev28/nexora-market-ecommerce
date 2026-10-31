<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Support\SampleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SampleCatalog::products() as $product) {
            $sellerProfile = SellerProfile::where('business_name', $product['seller'])->first();
            $category = Category::where('slug', $product['category'])->first();

            if (! $sellerProfile || ! $category) {
                // SellerSeeder / CategorySeeder should run before this one
                // (see DatabaseSeeder) — if either is missing, skip rather
                // than insert a product with a broken reference.
                continue;
            }

            $isFlash = in_array($product['slug'], ['nimbus-mechanical-keyboard', 'aeromax-elite-running-shoe']);
            $variantsMatrix = [];
            if (! empty($product['colors']) && ! empty($product['sizes'])) {
                foreach ($product['colors'] as $color) {
                    foreach ($product['sizes'] as $size) {
                        $key = "{$color}_{$size}";
                        $variantsMatrix[$key] = [
                            'color' => $color,
                            'size' => $size,
                            'sku' => strtoupper(substr($product['slug'], 0, 4)) . '-' . substr($color, 0, 2) . '-' . substr($size, 0, 2),
                            'price' => (float) $product['price'],
                            'stock' => rand(5, 30),
                        ];
                    }
                }
            }

            Product::updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'seller_id' => $sellerProfile->user_id,
                    'category_id' => $category->id,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'compare_price' => $product['comparePrice'],
                    'flash_sale_price' => $isFlash ? round($product['price'] * 0.75, 2) : null,
                    'flash_sale_ends_at' => $isFlash ? now()->addDays(3) : null,
                    'stock' => $product['stock'],
                    'weight' => rand(4, 18) / 10, // 0.40kg to 1.80kg
                    'max_purchase_limit' => 5,
                    'status' => 'active',
                    'gallery' => $product['gallery'],
                    'colors' => $product['colors'],
                    'sizes' => $product['sizes'],
                    'variants_data' => ! empty($variantsMatrix) ? $variantsMatrix : null,
                ]
            );
        }
    }
}

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

            Product::firstOrCreate(
                ['slug' => $product['slug']],
                [
                    'seller_id' => $sellerProfile->user_id,
                    'category_id' => $category->id,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'compare_price' => $product['comparePrice'],
                    'stock' => $product['stock'],
                    'status' => 'active',
                    'gallery' => $product['gallery'],
                    'colors' => $product['colors'],
                    'sizes' => $product['sizes'],
                ]
            );
        }
    }
}

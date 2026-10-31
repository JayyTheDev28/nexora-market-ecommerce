<?php

use App\Models\Category;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_seller')) {
            Schema::create('category_seller', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->unique(['seller_id', 'category_id']);
                $table->timestamps();
            });
        }

        // Add standard online shopping categories if not present
        $standardCategories = [
            ['slug' => 'electronics', 'name' => 'Electronics', 'icon' => 'devices'],
            ['slug' => 'fashion', 'name' => 'Fashion & Apparel', 'icon' => 'checkroom'],
            ['slug' => 'home-living', 'name' => 'Home & Living', 'icon' => 'chair'],
            ['slug' => 'beauty', 'name' => 'Health & Beauty', 'icon' => 'face_retouching_natural'],
            ['slug' => 'sports', 'name' => 'Sports & Outdoors', 'icon' => 'sports_soccer'],
            ['slug' => 'gaming', 'name' => 'Gaming & Hobbies', 'icon' => 'sports_esports'],
            ['slug' => 'accessories', 'name' => 'Jewelry & Accessories', 'icon' => 'watch'],
            ['slug' => 'kids', 'name' => 'Babies & Kids', 'icon' => 'child_care'],
            ['slug' => 'groceries', 'name' => 'Groceries & Food', 'icon' => 'local_grocery_store'],
            ['slug' => 'health', 'name' => 'Health & Wellness', 'icon' => 'health_and_safety'],
            ['slug' => 'automotive', 'name' => 'Automotive & Motor', 'icon' => 'directions_car'],
            ['slug' => 'pet-supplies', 'name' => 'Pet Supplies', 'icon' => 'pets'],
            ['slug' => 'books-stationery', 'name' => 'Books, Stationery & Crafts', 'icon' => 'menu_book'],
        ];

        foreach ($standardCategories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'icon' => $cat['icon']]
            );
        }

        // Backfill existing sellers with their selected categories (or matching their existing products)
        $sellers = User::where('role', 'seller')->with(['sellerProfile', 'products'])->get();
        $allCategories = Category::all();

        foreach ($sellers as $seller) {
            $catIds = collect();

            // Check existing products category_id
            $productCatIds = $seller->products->pluck('category_id')->filter();
            $catIds = $catIds->merge($productCatIds);

            // Match seller profile category text/slug
            if ($seller->sellerProfile && $seller->sellerProfile->category) {
                $matchedCat = $allCategories->first(function ($c) use ($seller) {
                    return strcasecmp($c->slug, $seller->sellerProfile->category) === 0
                        || strcasecmp($c->name, $seller->sellerProfile->category) === 0;
                });
                if ($matchedCat) {
                    $catIds->push($matchedCat->id);
                }
            }

            // If seller had no specific category yet, associate default initial categories
            if ($catIds->isEmpty()) {
                $catIds = $allCategories->take(3)->pluck('id');
            }

            foreach ($catIds->unique() as $catId) {
                DB::table('category_seller')->updateOrInsert(
                    ['seller_id' => $seller->id, 'category_id' => $catId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_seller');
    }
};

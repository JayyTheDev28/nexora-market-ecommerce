<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('status', 'active')
            ->with(['category', 'seller.sellerProfile'])
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category->slug,
                'seller' => $p->seller->sellerProfile->business_name ?? $p->seller->full_name,
                'price' => (float) $p->price,
                'comparePrice' => $p->compare_price ? (float) $p->compare_price : null,
                'stock' => $p->stock,
                'image' => $p->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($p->name),
                'url' => url("/products/{$p->id}"),
            ])
            ->values()
            ->all();

        $categories = Category::orderBy('name')->get(['slug', 'name', 'icon'])->toArray();

        // Passed through so the search box / category chip clicked elsewhere
        // (navbar search, home page category cards) pre-fills the filters —
        // the actual filtering itself still happens client-side in Alpine.
        $initialQuery = $request->query('q', '');
        $initialCategory = $request->query('category', '');

        return view('buyer.catalog.index', compact('products', 'categories', 'initialQuery', 'initialCategory'));
    }
}

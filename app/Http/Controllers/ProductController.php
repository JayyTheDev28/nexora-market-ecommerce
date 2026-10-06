<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, int $id)
    {
        $product = Product::with(['category', 'seller.sellerProfile'])->findOrFail($id);

        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->with('seller.sellerProfile')
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'seller' => $p->seller->sellerProfile->business_name ?? $p->seller->full_name,
                'price' => (float) $p->price,
                'comparePrice' => $p->compare_price ? (float) $p->compare_price : null,
                'image' => $p->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($p->name),
                'url' => url("/products/{$p->id}"),
            ])
            ->values()
            ->all();

        $productArray = [
            'id' => $product->id,
            'name' => $product->name,
            'seller' => $product->seller->sellerProfile->business_name ?? $product->seller->full_name,
            'price' => (float) $product->price,
            'comparePrice' => $product->compare_price ? (float) $product->compare_price : null,
            'rating' => null,
            'reviewCount' => 0,
            'stock' => $product->stock,
            'image' => $product->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($product->name),
            'gallery' => $product->gallery ?: [$product->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($product->name)],
            'colors' => $product->colors ?? [],
            'sizes' => $product->sizes ?? [],
            'description' => $product->description,
        ];

        return view('buyer.products.show', ['product' => $productArray, 'related' => $related]);
    }
}

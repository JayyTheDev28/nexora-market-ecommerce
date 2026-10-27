<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $seller = $request->user();

        $activeFlashSales = $seller->products()
            ->whereNotNull('flash_sale_price')
            ->where('flash_sale_price', '>', 0)
            ->where('flash_sale_ends_at', '>', now())
            ->latest()
            ->get();

        $expiredFlashSales = $seller->products()
            ->whereNotNull('flash_sale_price')
            ->where('flash_sale_price', '>', 0)
            ->where('flash_sale_ends_at', '<=', now())
            ->latest()
            ->get();

        $discountedProducts = $seller->products()
            ->whereNotNull('compare_price')
            ->where('compare_price', '>', 0)
            ->whereColumn('compare_price', '>', 'price')
            ->latest()
            ->get();

        $allProducts = $seller->products()->where('status', 'active')->orderBy('name')->get();

        return view('seller.promotions.index', compact(
            'activeFlashSales',
            'expiredFlashSales',
            'discountedProducts',
            'allProducts'
        ));
    }

    public function storeFlashSale(Request $request): RedirectResponse
    {
        $seller = $request->user();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'flash_sale_price' => ['required', 'numeric', 'min:1'],
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        $product = $seller->products()->findOrFail($validated['product_id']);

        if ((float) $validated['flash_sale_price'] >= (float) $product->price) {
            return back()->withErrors([
                'flash_sale_price' => "Flash sale price (₱{$validated['flash_sale_price']}) must be lower than the normal product price (₱{$product->price}).",
            ]);
        }

        $product->update([
            'flash_sale_price' => $validated['flash_sale_price'],
            'flash_sale_ends_at' => $validated['ends_at'],
        ]);

        return redirect()->route('seller.promotions.index')
            ->with('status', "Flash sale activated for '{$product->name}' until " . date('M j, Y H:i', strtotime($validated['ends_at'])) . ".");
    }

    public function endFlashSale(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $product->update([
            'flash_sale_price' => null,
            'flash_sale_ends_at' => null,
        ]);

        return redirect()->route('seller.promotions.index')
            ->with('status', "Flash sale for '{$product->name}' ended. Regular price restored.");
    }

    public function setDiscount(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'sale_price' => ['required', 'numeric', 'min:1'],
            'original_price' => ['required', 'numeric', 'gt:sale_price'],
        ]);

        $product->update([
            'price' => $validated['sale_price'],
            'compare_price' => $validated['original_price'],
        ]);

        return redirect()->route('seller.promotions.index')
            ->with('status', "Discount applied to '{$product->name}'.");
    }
}

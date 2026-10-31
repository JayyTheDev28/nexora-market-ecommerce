<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $seller = $request->user();

        $query = $seller->products()->with(['category', 'storeCategory'])->latest();

        if ($stockStatus = $request->query('filter')) {
            if ($stockStatus === 'low_stock') {
                $query->where('status', 'active')
                      ->whereColumn('stock', '<=', 'low_stock_threshold')
                      ->where('stock', '>', 0);
            } elseif ($stockStatus === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            } elseif ($stockStatus === 'in_stock') {
                $query->whereColumn('stock', '>', 'low_stock_threshold');
            }
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate(20)->withQueryString();

        $metrics = [
            'total_items' => $seller->products()->count(),
            'total_stock_units' => (int) $seller->products()->sum('stock'),
            'low_stock_count' => $seller->products()->where('status', 'active')->whereColumn('stock', '<=', 'low_stock_threshold')->where('stock', '>', 0)->count(),
            'out_of_stock_count' => $seller->products()->where('stock', '<=', 0)->count(),
        ];

        return view('seller.inventory.index', compact('products', 'metrics'));
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'adjustment_type' => ['required', 'in:set,add,subtract'],
            'quantity' => ['required', 'integer', 'min:0'],
            'variant_index' => ['nullable', 'integer'],
            'threshold' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $currentStock = $product->stock;
        $qty = (int) $validated['quantity'];

        $newStock = match ($validated['adjustment_type']) {
            'set' => $qty,
            'add' => $currentStock + $qty,
            'subtract' => max(0, $currentStock - $qty),
        };

        // If adjusting a variant in variants_data
        if (isset($validated['variant_index']) && is_array($product->variants_data)) {
            $variants = $product->variants_data;
            $idx = (int) $validated['variant_index'];
            if (isset($variants[$idx])) {
                $vStock = (int) ($variants[$idx]['stock'] ?? 0);
                $variants[$idx]['stock'] = match ($validated['adjustment_type']) {
                    'set' => $qty,
                    'add' => $vStock + $qty,
                    'subtract' => max(0, $vStock - $qty),
                };
                $product->variants_data = $variants;
                // re-sum variant stock
                $newStock = array_sum(array_column($variants, 'stock'));
            }
        }

        $updates = ['stock' => $newStock];
        if (isset($validated['threshold'])) {
            $updates['low_stock_threshold'] = (int) $validated['threshold'];
        }

        $product->update($updates);

        $reasonText = ! empty($validated['reason']) ? " (Reason: {$validated['reason']})" : '';
        return redirect()->route('seller.inventory.index')
            ->with('status', "Stock for '{$product->name}' adjusted to {$newStock} units{$reasonText}.");
    }
}

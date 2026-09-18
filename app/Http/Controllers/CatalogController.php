<?php

namespace App\Http\Controllers;

use App\Support\SampleCatalog;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $products = array_map(function ($p) {
            $p['url'] = url("/products/{$p['id']}");
            return $p;
        }, SampleCatalog::products());

        $categories = SampleCatalog::categories();

        // Passed through so the search box / category chip clicked elsewhere
        // (navbar search, home page category cards) pre-fills the filters —
        // the actual filtering itself still happens client-side in Alpine.
        $initialQuery = $request->query('q', '');
        $initialCategory = $request->query('category', '');

        return view('buyer.catalog.index', compact('products', 'categories', 'initialQuery', 'initialCategory'));
    }
}

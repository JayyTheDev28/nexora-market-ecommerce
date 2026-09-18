<?php

namespace App\Http\Controllers;

use App\Support\SampleCatalog;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, int $id)
    {
        $product = SampleCatalog::find($id);

        abort_if(! $product, 404);

        $related = SampleCatalog::relatedTo($product);
        $related = array_map(function ($p) {
            $p['url'] = url("/products/{$p['id']}");
            return $p;
        }, $related);

        return view('buyer.products.show', compact('product', 'related'));
    }
}

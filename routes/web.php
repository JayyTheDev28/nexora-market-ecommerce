<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Buyer registration — frontend only for now (no controller, no DB, no auth).
// Form has no action; submission handling comes in a later phase.
Route::get('/register', function () {
    return view('buyer.auth.register', ['hideFooter' => true]);
})->name('buyer.register');

// Static preview of the pending-approval screen the buyer will land on
// after submitting — not yet wired to an actual form submission.
Route::get('/register/pending', function () {
    return view('buyer.auth.pending');
})->name('buyer.pending');

// Buyer login — frontend only for now (no controller, no auth backend).
// Submit is currently a no-op; wiring this up is a later phase.
Route::get('/login', function () {
    return view('buyer.auth.login', ['hideFooter' => true]);
})->name('login');

// Shopping flow — all still frontend-only. Product/catalog data comes from
// App\Support\SampleCatalog; cart and orders live in the browser via the
// Alpine stores in resources/js/app.js (localStorage), not a database.
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::get('/buyer/orders', [OrdersController::class, 'index'])->name('buyer.orders');


<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Buyer;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Seller;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Authentication — registration, login, email verification
|--------------------------------------------------------------------------
| Real database-backed auth. Covers buyer/seller/sorting_center
| registration (courier registration is a later phase). Admin accounts are
| never self-registered — see database/seeders/AdminUserSeeder.php.
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('buyer.register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Waiting-for-admin-approval screen — reached after registering/verifying,
    // or on login while approval_status is still 'pending'.
    Route::get('/register/pending', function () {
        return view('buyer.auth.pending');
    })->name('buyer.pending');

    Route::get('/email/verify', [EmailVerificationNotificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Role dashboards
|--------------------------------------------------------------------------
| The 'role' middleware checks, in order: signed in -> email verified ->
| not disapproved -> not pending -> correct role. Anyone hitting another
| role's dashboard gets redirected to their own rather than a 403.
|
| These are placeholder interfaces for now (except the admin's application
| approvals, which is functional) — they exist to confirm that each role
| lands in the right place after logging in.
*/

Route::middleware('role:buyer')->group(function () {
    Route::get('/buyer/dashboard', [DashboardController::class, 'buyer'])->name('buyer.dashboard');

    Route::get('/buyer/account', [Buyer\AccountController::class, 'show'])->name('buyer.account');
    Route::put('/buyer/account/profile', [Buyer\AccountController::class, 'updateProfile'])->name('buyer.account.profile');
    Route::put('/buyer/account/address', [Buyer\AccountController::class, 'updateAddress'])->name('buyer.account.address');
    Route::put('/buyer/account/password', [Buyer\AccountController::class, 'updatePassword'])->name('buyer.account.password');
});

Route::middleware('role:seller')->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'seller'])->name('dashboard');

    Route::get('/products', [Seller\ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [Seller\ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [Seller\ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [Seller\ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [Seller\ProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/archive', [Seller\ProductController::class, 'archive'])->name('products.archive');
    Route::post('/products/{product}/activate', [Seller\ProductController::class, 'activate'])->name('products.activate');

    Route::get('/orders', [Seller\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Seller\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/advance', [Seller\OrderController::class, 'advance'])->name('orders.advance');

    Route::get('/reports', [Seller\ReportController::class, 'index'])->name('reports.index');
    Route::get('/feedback', [Seller\FeedbackController::class, 'index'])->name('feedback.index');
});

Route::get('/logistics/dashboard', [DashboardController::class, 'logistics'])
    ->middleware('role:sorting_center')->name('logistics.dashboard');

Route::middleware('role:admin')->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
    Route::post('/admin/applications/{user}/approve', [ApplicationController::class, 'approve'])->name('admin.applications.approve');
    Route::post('/admin/applications/{user}/disapprove', [ApplicationController::class, 'disapprove'])->name('admin.applications.disapprove');
});

/*
|--------------------------------------------------------------------------
| Shopping flow
|--------------------------------------------------------------------------
| Catalog/product browsing stays public. Cart, checkout, and orders now
| require a fully cleared account (role middleware: verified + approved +
| active) since they're tied to a real account in the database — not
| fully public localStorage state anymore.
*/
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');

Route::middleware('role')->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    Route::patch('/cart/select-all', [CartController::class, 'selectAll'])->name('cart.select-all');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/buyer/orders', [OrdersController::class, 'index'])->name('buyer.orders');
    Route::post('/buyer/orders/{order}/confirm-receipt', [OrdersController::class, 'confirmReceipt'])->name('buyer.orders.confirm-receipt');
    Route::post('/buyer/orders/{order}/feedback', [OrdersController::class, 'submitFeedback'])->name('buyer.orders.feedback');
    Route::post('/buyer/orders/{order}/dispute', [OrdersController::class, 'submitDispute'])->name('buyer.orders.dispute');
});



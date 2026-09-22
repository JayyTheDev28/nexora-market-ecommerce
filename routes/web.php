<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
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

Route::get('/buyer/dashboard', [DashboardController::class, 'buyer'])
    ->middleware('role:buyer')->name('buyer.dashboard');

Route::get('/seller/dashboard', [DashboardController::class, 'seller'])
    ->middleware('role:seller')->name('seller.dashboard');

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
| Product/catalog data comes from App\Support\SampleCatalog. Cart and
| orders still live client-side via the Alpine stores in resources/js/app.js
| (localStorage) — this is the next thing to migrate onto the database now
| that accounts are real, but isn't wired up yet.
*/
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::get('/buyer/orders', [OrdersController::class, 'index'])->name('buyer.orders');


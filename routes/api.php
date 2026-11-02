<?php

use App\Http\Controllers\Api\LocationApiController;
use App\Http\Controllers\Api\RiderApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Locations & PSGC API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('locations')->group(function () {
    Route::get('/provinces', [LocationApiController::class, 'provinces'])->name('api.locations.provinces');
    Route::get('/provinces/{provinceCode}/cities', [LocationApiController::class, 'cities'])->name('api.locations.cities');
    Route::get('/cities/{cityCode}/barangays', [LocationApiController::class, 'barangays'])->name('api.locations.barangays');
});

/*
|--------------------------------------------------------------------------
| Delivery Rider REST API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1/rider')->group(function () {
    Route::post('/login', [RiderApiController::class, 'login'])->name('api.rider.login');

    Route::middleware(['auth', 'role:rider,courier'])->group(function () {
        Route::get('/profile', [RiderApiController::class, 'profile'])->name('api.rider.profile');
        Route::post('/availability', [RiderApiController::class, 'toggleAvailability'])->name('api.rider.availability');
        
        // 60s Job Offers
        Route::get('/offers', [RiderApiController::class, 'offers'])->name('api.rider.offers');
        Route::post('/offers/{offer}/accept', [RiderApiController::class, 'acceptOffer'])->name('api.rider.offers.accept');
        Route::post('/offers/{offer}/decline', [RiderApiController::class, 'declineOffer'])->name('api.rider.offers.decline');

        // Operations
        Route::post('/pickup', [RiderApiController::class, 'confirmPickup'])->name('api.rider.pickup');
        Route::post('/deliveries/{order}/complete', [RiderApiController::class, 'completeDelivery'])->name('api.rider.deliveries.complete');
        Route::post('/deliveries/{order}/fail', [RiderApiController::class, 'failDelivery'])->name('api.rider.deliveries.fail');

        // Earnings & Remittance
        Route::get('/earnings', [RiderApiController::class, 'earnings'])->name('api.rider.earnings');
        Route::post('/remittance', [RiderApiController::class, 'submitRemittance'])->name('api.rider.remittance');
    });
});

/*
|--------------------------------------------------------------------------
| Mobile API Routes (v1)
|--------------------------------------------------------------------------
*/
Route::prefix('mobile/v1')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        // Buyer auth
        Route::post('/buyer/register', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'buyerRegister'])->name('api.mobile.buyer.register');
        Route::post('/buyer/login', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'buyerLogin'])->name('api.mobile.buyer.login');
        Route::post('/buyer/email/verify', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'verifyEmailOtp'])->name('api.mobile.buyer.email.verify');
        Route::post('/buyer/email/resend', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'resendEmailOtp'])->name('api.mobile.buyer.email.resend');

        // Rider auth
        Route::post('/rider/login', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'riderLogin'])->name('api.mobile.rider.login');
        Route::post('/rider/activate/{token}', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'riderActivate'])->name('api.mobile.rider.activate');

        // Authenticated session routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'me'])->name('api.mobile.auth.me');
            Route::post('/logout', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'logout'])->name('api.mobile.auth.logout');
        });
    });

    // Public buyer routes
    Route::prefix('buyer')->group(function () {
        Route::get('/catalog', [\App\Http\Controllers\Api\Mobile\Buyer\CatalogController::class, 'index'])->name('api.mobile.buyer.catalog');
        Route::get('/categories', [\App\Http\Controllers\Api\Mobile\Buyer\CatalogController::class, 'categories'])->name('api.mobile.buyer.categories');
        Route::get('/products/{id}', [\App\Http\Controllers\Api\Mobile\Buyer\ProductController::class, 'show'])->name('api.mobile.buyer.products.show');
        Route::get('/stores/{seller}', [\App\Http\Controllers\Api\Mobile\Buyer\StoreController::class, 'show'])->name('api.mobile.buyer.stores.show');
    });

    // Protected buyer routes
    Route::middleware(['auth:sanctum', 'role:buyer'])->prefix('buyer')->group(function () {
        // Account & Profile
        Route::get('/account', [\App\Http\Controllers\Api\Mobile\Buyer\AccountController::class, 'show'])->name('api.mobile.buyer.account');
        Route::put('/account/profile', [\App\Http\Controllers\Api\Mobile\Buyer\AccountController::class, 'updateProfile'])->name('api.mobile.buyer.account.profile');
        Route::put('/account/password', [\App\Http\Controllers\Api\Mobile\Buyer\AccountController::class, 'updatePassword'])->name('api.mobile.buyer.account.password');
        Route::post('/account/email/request-otp', [\App\Http\Controllers\Api\Mobile\Buyer\AccountController::class, 'requestEmailChange'])->name('api.mobile.buyer.account.email.request');
        Route::post('/account/email/verify-otp', [\App\Http\Controllers\Api\Mobile\Buyer\AccountController::class, 'verifyEmailChange'])->name('api.mobile.buyer.account.email.verify');
        Route::post('/account/phone/request-otp', [\App\Http\Controllers\Api\Mobile\Buyer\AccountController::class, 'requestPhoneChange'])->name('api.mobile.buyer.account.phone.request');
        Route::post('/account/phone/verify-otp', [\App\Http\Controllers\Api\Mobile\Buyer\AccountController::class, 'verifyPhoneChange'])->name('api.mobile.buyer.account.phone.verify');

        // Address Book
        Route::get('/addresses', [\App\Http\Controllers\Api\Mobile\Buyer\AddressController::class, 'index'])->name('api.mobile.buyer.addresses.index');
        Route::post('/addresses', [\App\Http\Controllers\Api\Mobile\Buyer\AddressController::class, 'store'])->name('api.mobile.buyer.addresses.store');
        Route::put('/addresses/{address}', [\App\Http\Controllers\Api\Mobile\Buyer\AddressController::class, 'update'])->name('api.mobile.buyer.addresses.update');
        Route::post('/addresses/{address}/default', [\App\Http\Controllers\Api\Mobile\Buyer\AddressController::class, 'setDefault'])->name('api.mobile.buyer.addresses.default');
        Route::delete('/addresses/{address}', [\App\Http\Controllers\Api\Mobile\Buyer\AddressController::class, 'destroy'])->name('api.mobile.buyer.addresses.destroy');

        // Wishlist & Store Follow
        Route::get('/wishlist', [\App\Http\Controllers\Api\Mobile\Buyer\WishlistController::class, 'index'])->name('api.mobile.buyer.wishlist.index');
        Route::post('/wishlist/{product}/toggle', [\App\Http\Controllers\Api\Mobile\Buyer\WishlistController::class, 'toggle'])->name('api.mobile.buyer.wishlist.toggle');
        Route::get('/stores/followed', [\App\Http\Controllers\Api\Mobile\Buyer\WishlistController::class, 'followedStores'])->name('api.mobile.buyer.stores.followed');
        Route::post('/stores/{seller}/follow-toggle', [\App\Http\Controllers\Api\Mobile\Buyer\WishlistController::class, 'toggleFollow'])->name('api.mobile.buyer.stores.follow-toggle');

        // Vouchers
        Route::get('/vouchers', [\App\Http\Controllers\Api\Mobile\Buyer\VoucherController::class, 'index'])->name('api.mobile.buyer.vouchers.index');
        Route::post('/vouchers/{voucher}/claim', [\App\Http\Controllers\Api\Mobile\Buyer\VoucherController::class, 'claim'])->name('api.mobile.buyer.vouchers.claim');

        // Cart
        Route::get('/cart', [\App\Http\Controllers\Api\Mobile\Buyer\CartController::class, 'index'])->name('api.mobile.buyer.cart.index');
        Route::post('/cart/items', [\App\Http\Controllers\Api\Mobile\Buyer\CartController::class, 'store'])->name('api.mobile.buyer.cart.items.store');
        Route::patch('/cart/items/{cartItem}', [\App\Http\Controllers\Api\Mobile\Buyer\CartController::class, 'update'])->name('api.mobile.buyer.cart.items.update');
        Route::delete('/cart/items/{cartItem}', [\App\Http\Controllers\Api\Mobile\Buyer\CartController::class, 'destroy'])->name('api.mobile.buyer.cart.items.destroy');
        Route::patch('/cart/select-all', [\App\Http\Controllers\Api\Mobile\Buyer\CartController::class, 'selectAll'])->name('api.mobile.buyer.cart.select-all');
        Route::patch('/cart/select-store/{sellerId}', [\App\Http\Controllers\Api\Mobile\Buyer\CartController::class, 'selectStore'])->name('api.mobile.buyer.cart.select-store');

        // Checkout
        Route::get('/checkout/summary', [\App\Http\Controllers\Api\Mobile\Buyer\CheckoutController::class, 'summary'])->name('api.mobile.buyer.checkout.summary');
        Route::post('/checkout', [\App\Http\Controllers\Api\Mobile\Buyer\CheckoutController::class, 'store'])->name('api.mobile.buyer.checkout.store');

        // Orders
        Route::get('/orders', [\App\Http\Controllers\Api\Mobile\Buyer\OrderController::class, 'index'])->name('api.mobile.buyer.orders.index');
        Route::get('/orders/{order}', [\App\Http\Controllers\Api\Mobile\Buyer\OrderController::class, 'show'])->name('api.mobile.buyer.orders.show');
        Route::post('/orders/{order}/cancel', [\App\Http\Controllers\Api\Mobile\Buyer\OrderController::class, 'cancelOrder'])->name('api.mobile.buyer.orders.cancel');
        Route::post('/orders/{order}/confirm-receipt', [\App\Http\Controllers\Api\Mobile\Buyer\OrderController::class, 'confirmReceipt'])->name('api.mobile.buyer.orders.confirm-receipt');
        Route::post('/orders/{order}/return', [\App\Http\Controllers\Api\Mobile\Buyer\OrderController::class, 'submitReturn'])->name('api.mobile.buyer.orders.return');
        Route::post('/orders/{order}/review', [\App\Http\Controllers\Api\Mobile\Buyer\OrderController::class, 'submitReview'])->name('api.mobile.buyer.orders.review');

        // Chat
        Route::get('/chat', [\App\Http\Controllers\Api\Mobile\Buyer\ChatController::class, 'index'])->name('api.mobile.buyer.chat.index');
        Route::get('/chat/{conversation}', [\App\Http\Controllers\Api\Mobile\Buyer\ChatController::class, 'show'])->name('api.mobile.buyer.chat.show');
        Route::post('/chat/start', [\App\Http\Controllers\Api\Mobile\Buyer\ChatController::class, 'startWithSeller'])->name('api.mobile.buyer.chat.start');
        Route::post('/chat/{conversation}/message', [\App\Http\Controllers\Api\Mobile\Buyer\ChatController::class, 'sendMessage'])->name('api.mobile.buyer.chat.message');
    });

    // Protected rider routes
    Route::middleware(['auth:sanctum', 'role:rider,courier'])->prefix('rider')->group(function () {
        Route::get('/profile', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'profile'])->name('api.mobile.rider.profile');
        Route::post('/availability', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'toggleAvailability'])->name('api.mobile.rider.availability');
        Route::get('/offers', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'offers'])->name('api.mobile.rider.offers');
        Route::post('/offers/{offer}/accept', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'acceptOffer'])->name('api.mobile.rider.offers.accept');
        Route::post('/offers/{offer}/decline', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'declineOffer'])->name('api.mobile.rider.offers.decline');
        Route::post('/pickup', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'confirmPickup'])->name('api.mobile.rider.pickup');
        Route::get('/deliveries/active', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'activeDeliveries'])->name('api.mobile.rider.deliveries.active');
        Route::get('/deliveries/history', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'deliveryHistory'])->name('api.mobile.rider.deliveries.history');
        Route::post('/deliveries/{order}/complete', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'completeDelivery'])->name('api.mobile.rider.deliveries.complete');
        Route::post('/deliveries/{order}/fail', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'failDelivery'])->name('api.mobile.rider.deliveries.fail');
        Route::get('/earnings', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'earnings'])->name('api.mobile.rider.earnings');
        Route::post('/remittance', [\App\Http\Controllers\Api\Mobile\Rider\RiderController::class, 'submitRemittance'])->name('api.mobile.rider.remittance');
    });
});


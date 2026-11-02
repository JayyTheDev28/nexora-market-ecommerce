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
});


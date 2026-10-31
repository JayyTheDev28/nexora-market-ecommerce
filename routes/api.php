<?php

use App\Http\Controllers\Api\RiderApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery Rider REST API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1/rider')->group(function () {
    Route::post('/login', [RiderApiController::class, 'login'])->name('api.rider.login');

    Route::middleware(['auth', 'role:courier'])->group(function () {
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

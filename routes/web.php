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
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Auth\RiderActivationController;
use App\Http\Controllers\Courier;
use App\Http\Controllers\Logistics;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Seller;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Legal & Compliance (Publicly viewable)
|--------------------------------------------------------------------------
*/
Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms-of-service', [LegalController::class, 'terms'])->name('terms');
Route::get('/privacy', fn () => redirect()->route('privacy'));
Route::get('/terms', fn () => redirect()->route('terms'));

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
    Route::post('/email/verify', [VerifyEmailController::class, 'verifyOtp'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verifyLink'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify.link');

// Rider Account Activation (Public token link)
Route::get('/rider/activate/{token}', [RiderActivationController::class, 'show'])->name('rider.activate');
Route::post('/rider/activate/{token}', [RiderActivationController::class, 'activate'])->name('rider.activate.post');

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

    // B1: Account & Profile
    Route::get('/buyer/account', [Buyer\AccountController::class, 'show'])->name('buyer.account');
    Route::put('/buyer/account/profile', [Buyer\AccountController::class, 'updateProfile'])->name('buyer.account.profile');
    Route::put('/buyer/account/password', [Buyer\AccountController::class, 'updatePassword'])->name('buyer.account.password');
    Route::post('/buyer/account/email/request-otp', [Buyer\AccountController::class, 'requestEmailChange'])->name('buyer.account.email.request');
    Route::post('/buyer/account/email/verify-otp', [Buyer\AccountController::class, 'verifyEmailChange'])->name('buyer.account.email.verify');
    Route::post('/buyer/account/phone/request-otp', [Buyer\AccountController::class, 'requestPhoneChange'])->name('buyer.account.phone.request');
    Route::post('/buyer/account/phone/verify-otp', [Buyer\AccountController::class, 'verifyPhoneChange'])->name('buyer.account.phone.verify');

    // B2: Address Book
    Route::post('/buyer/addresses', [Buyer\AddressController::class, 'store'])->name('buyer.addresses.store');
    Route::put('/buyer/addresses/{address}', [Buyer\AddressController::class, 'update'])->name('buyer.addresses.update');
    Route::post('/buyer/addresses/{address}/default', [Buyer\AddressController::class, 'setDefault'])->name('buyer.addresses.set-default');
    Route::delete('/buyer/addresses/{address}', [Buyer\AddressController::class, 'destroy'])->name('buyer.addresses.destroy');
});

Route::middleware('role:seller')->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'seller'])->name('dashboard');

    // Store Profile & Vacation Mode
    Route::get('/store', [Seller\StoreController::class, 'edit'])->name('store.edit');
    Route::put('/store', [Seller\StoreController::class, 'update'])->name('store.update');
    Route::post('/store/vacation', [Seller\StoreController::class, 'toggleVacation'])->name('store.vacation');

    // Store Categories
    Route::get('/categories', [Seller\CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [Seller\CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [Seller\CategoryController::class, 'update'])->name('categories.update');
    Route::post('/categories/{category}/toggle', [Seller\CategoryController::class, 'toggleVisibility'])->name('categories.toggle');
    Route::delete('/categories/{category}', [Seller\CategoryController::class, 'destroy'])->name('categories.destroy');

    // Products & Variants
    Route::get('/products', [Seller\ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [Seller\ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [Seller\ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [Seller\ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [Seller\ProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/duplicate', [Seller\ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::post('/products/{product}/archive', [Seller\ProductController::class, 'archive'])->name('products.archive');
    Route::post('/products/{product}/activate', [Seller\ProductController::class, 'activate'])->name('products.activate');
    Route::delete('/products/{product}', [Seller\ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/bulk', [Seller\ProductController::class, 'bulk'])->name('products.bulk');

    // Inventory Management
    Route::get('/inventory', [Seller\InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/{product}/adjust', [Seller\InventoryController::class, 'adjustStock'])->name('inventory.adjust');

    // Promotions & Flash Sales
    Route::get('/promotions', [Seller\PromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions/flash-sale', [Seller\PromotionController::class, 'storeFlashSale'])->name('promotions.flash-sale.store');
    Route::post('/promotions/{product}/end-flash-sale', [Seller\PromotionController::class, 'endFlashSale'])->name('promotions.flash-sale.end');
    Route::post('/promotions/{product}/discount', [Seller\PromotionController::class, 'setDiscount'])->name('promotions.discount.set');

    // Store Vouchers
    Route::get('/vouchers', [Seller\VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchers', [Seller\VoucherController::class, 'store'])->name('vouchers.store');
    Route::put('/vouchers/{voucher}', [Seller\VoucherController::class, 'update'])->name('vouchers.update');
    Route::post('/vouchers/{voucher}/toggle', [Seller\VoucherController::class, 'toggle'])->name('vouchers.toggle');
    Route::get('/vouchers/{voucher}/claims', [Seller\VoucherController::class, 'claims'])->name('vouchers.claims');

    // Orders & Waybills & Cancellations
    Route::get('/orders', [Seller\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Seller\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/advance', [Seller\OrderController::class, 'advance'])->name('orders.advance');
    Route::post('/orders/{order}/cancel', [Seller\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{order}/waybill', [Seller\OrderController::class, 'waybill'])->name('orders.waybill');

    // Returns & Refunds
    Route::get('/returns', [Seller\ReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{dispute}', [Seller\ReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{dispute}/approve', [Seller\ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('/returns/{dispute}/reject', [Seller\ReturnController::class, 'reject'])->name('returns.reject');

    // Feedback & Reviews
    Route::get('/feedback', [Seller\FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/feedback/{review}/reply', [Seller\FeedbackController::class, 'reply'])->name('feedback.reply');

    // Buyer-Seller Chat
    Route::get('/chat', [Seller\ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/{conversation}/message', [Seller\ChatController::class, 'sendMessage'])->name('chat.message');

    // Financial Reports & Payouts
    Route::get('/reports', [Seller\ReportController::class, 'index'])->name('reports.index');
    Route::post('/payouts/request', [Seller\ReportController::class, 'requestPayout'])->name('payouts.request');
});

// Logistics Partner Public Registration & Verification
Route::prefix('logistics')->name('logistics.')->group(function () {
    Route::get('/register', [\App\Http\Controllers\Logistics\RegistrationController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Logistics\RegistrationController::class, 'register'])
        ->middleware('throttle:6,1')
        ->name('register.store');

    Route::get('/verify-otp', [\App\Http\Controllers\Logistics\RegistrationController::class, 'showOtpForm'])->name('otp.notice');
    Route::post('/verify-otp', [\App\Http\Controllers\Logistics\RegistrationController::class, 'verifyOtp'])->name('otp.verify');
    Route::post('/resend-otp', [\App\Http\Controllers\Logistics\RegistrationController::class, 'resendOtp'])
        ->middleware('throttle:3,1')
        ->name('otp.resend');

    Route::get('/pending', [\App\Http\Controllers\Logistics\RegistrationController::class, 'pending'])->name('pending');

    Route::middleware('auth')->group(function () {
        Route::get('/resubmit', [\App\Http\Controllers\Logistics\RegistrationController::class, 'showResubmitForm'])->name('resubmit');
        Route::post('/resubmit', [\App\Http\Controllers\Logistics\RegistrationController::class, 'resubmit'])->name('resubmit.store');
    });
});

Route::middleware(['role:sorting_center', 'logistics'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'logistics'])->name('dashboard');

    // Rider Management
    Route::get('/riders', [Logistics\RiderController::class, 'index'])->name('riders.index');
    Route::get('/riders/create', [Logistics\RiderController::class, 'create'])->name('riders.create');
    Route::post('/riders', [Logistics\RiderController::class, 'store'])->name('riders.store');
    Route::get('/riders/{rider}', [Logistics\RiderController::class, 'show'])->name('riders.show');
    Route::post('/riders/{rider}/resend-activation', [Logistics\RiderController::class, 'resendActivation'])->name('riders.resend-activation');
    Route::post('/riders/{rider}/approve', [Logistics\RiderController::class, 'approve'])->name('riders.approve');
    Route::post('/riders/{rider}/disapprove', [Logistics\RiderController::class, 'disapprove'])->name('riders.disapprove');
    Route::post('/riders/{rider}/toggle-status', [Logistics\RiderController::class, 'toggleStatus'])->name('riders.toggle-status');
    Route::post('/riders/{rider}/assign-areas', [Logistics\RiderController::class, 'assignAreas'])->name('riders.assign-areas');

    // Delivery Areas
    Route::post('/areas', [Logistics\RiderController::class, 'storeArea'])->name('areas.store');
    Route::delete('/areas/{area}', [Logistics\RiderController::class, 'destroyArea'])->name('areas.destroy');

    // Pickup Requests
    Route::get('/pickups', [Logistics\PickupController::class, 'index'])->name('pickups.index');
    Route::get('/pickups/{order}', [Logistics\PickupController::class, 'show'])->name('pickups.show');
    Route::post('/pickups/{order}/schedule', [Logistics\PickupController::class, 'schedule'])->name('pickups.schedule');
    Route::post('/pickups/{order}/confirm', [Logistics\PickupController::class, 'confirmPickup'])->name('pickups.confirm');
    Route::post('/pickups/{order}/reject', [Logistics\PickupController::class, 'reject'])->name('pickups.reject');

    // Incoming Parcels
    Route::get('/incoming', [Logistics\IncomingController::class, 'index'])->name('incoming.index');
    Route::post('/incoming/scan', [Logistics\IncomingController::class, 'scan'])->name('incoming.scan');
    Route::post('/incoming/{order}/flag', [Logistics\IncomingController::class, 'flag'])->name('incoming.flag');

    // Sorting Hub
    Route::get('/sorting', [Logistics\SortingController::class, 'index'])->name('sorting.index');
    Route::post('/sorting/{order}/confirm', [Logistics\SortingController::class, 'confirm'])->name('sorting.confirm');
    Route::post('/sorting/batch', [Logistics\SortingController::class, 'batchSort'])->name('sorting.batch');

    // Rider Assignments
    Route::get('/assignments', [Logistics\AssignmentController::class, 'index'])->name('assignments.index');
    Route::post('/assignments/{order}/assign', [Logistics\AssignmentController::class, 'assign'])->name('assignments.assign');
    Route::post('/assignments/areas/{area}/batch', [Logistics\AssignmentController::class, 'batchAreaAssign'])->name('assignments.batch-area');

    // Delivery Monitoring
    Route::get('/deliveries', [Logistics\MonitoringController::class, 'index'])->name('deliveries.index');
    Route::get('/deliveries/{order}', [Logistics\MonitoringController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{order}/resolve', [Logistics\MonitoringController::class, 'resolveException'])->name('deliveries.resolve');

    // Reports
    Route::get('/reports', [Logistics\ReportController::class, 'index'])->name('reports.index');

    // COD Remittances Reconciliation
    Route::get('/remittances', [Logistics\RemittanceController::class, 'index'])->name('remittances.index');
    Route::post('/remittances/{remittance}/review', [Logistics\RemittanceController::class, 'review'])->name('remittances.review');
    Route::post('/riders/{rider}/vehicle-verification', [Logistics\RiderController::class, 'verifyVehicle'])->name('riders.verify-vehicle');
    Route::post('/orders/{order}/override-status', [Logistics\RiderController::class, 'overrideStatus'])->name('orders.override-status');

    // Account & Settings
    Route::get('/account', [Logistics\AccountController::class, 'show'])->name('account.show');
    Route::post('/account/profile', [Logistics\AccountController::class, 'updateProfile'])->name('account.profile');
    Route::post('/account/address', [Logistics\AccountController::class, 'updateAddress'])->name('account.address');
    Route::post('/account/password', [Logistics\AccountController::class, 'updatePassword'])->name('account.password');
});

Route::middleware(['admin', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Applications & Approvals
    Route::get('/applications', [\App\Http\Controllers\Admin\ApplicationController::class, 'index'])->name('applications.index');
    Route::post('/applications/{user}/approve', [\App\Http\Controllers\Admin\ApplicationController::class, 'approve'])->name('applications.approve');
    Route::post('/applications/{user}/disapprove', [\App\Http\Controllers\Admin\ApplicationController::class, 'disapprove'])->name('applications.disapprove');

    // User Accounts Management
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/status', [\App\Http\Controllers\Admin\UserController::class, 'updateStatus'])->name('users.status');

    // Seller Compliance & Warnings
    Route::get('/compliance', [\App\Http\Controllers\Admin\ComplianceController::class, 'index'])->name('compliance.index');
    Route::post('/compliance/warnings', [\App\Http\Controllers\Admin\ComplianceController::class, 'storeWarning'])->name('compliance.warnings.store');
    Route::post('/compliance/products/{product}/toggle-status', [\App\Http\Controllers\Admin\ComplianceController::class, 'toggleProductStatus'])->name('compliance.products.toggle-status');

    // Complaints & Disputes
    Route::get('/disputes', [\App\Http\Controllers\Admin\DisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{dispute}', [\App\Http\Controllers\Admin\DisputeController::class, 'show'])->name('disputes.show');
    Route::post('/disputes/{dispute}/resolve', [\App\Http\Controllers\Admin\DisputeController::class, 'resolve'])->name('disputes.resolve');

    // 10% Commissions & Settlements
    Route::get('/commissions', [\App\Http\Controllers\Admin\CommissionController::class, 'index'])->name('commissions.index');
    Route::post('/commissions/{commission}/settle', [\App\Http\Controllers\Admin\CommissionController::class, 'settle'])->name('commissions.settle');
    Route::post('/commissions/batch-settle', [\App\Http\Controllers\Admin\CommissionController::class, 'batchSettle'])->name('commissions.batch-settle');

    // Analytics & Financial Reports
    Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [\App\Http\Controllers\Admin\ReportController::class, 'export'])->name('reports.export');

    // Platform Settings & Announcements
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/platform', [\App\Http\Controllers\Admin\SettingController::class, 'updatePlatformSettings'])->name('settings.platform.update');
    Route::post('/settings/announcements', [\App\Http\Controllers\Admin\SettingController::class, 'storeAnnouncement'])->name('settings.announcements.store');
    Route::put('/settings/announcements/{announcement}', [\App\Http\Controllers\Admin\SettingController::class, 'updateAnnouncement'])->name('settings.announcements.update');
    Route::delete('/settings/announcements/{announcement}', [\App\Http\Controllers\Admin\SettingController::class, 'destroyAnnouncement'])->name('settings.announcements.destroy');

    // Read-Only Audit Log Viewer
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');

    // Admin Profile & Security
    Route::get('/account', [\App\Http\Controllers\Admin\AccountController::class, 'show'])->name('account.show');
    Route::post('/account/profile', [\App\Http\Controllers\Admin\AccountController::class, 'updateProfile'])->name('account.profile');
    Route::post('/account/password', [\App\Http\Controllers\Admin\AccountController::class, 'updatePassword'])->name('account.password');
});

Route::middleware('role:courier')->prefix('courier')->name('courier.')->group(function () {
    Route::get('/dashboard', [Courier\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/toggle-online', [Courier\DashboardController::class, 'toggleOnline'])->name('toggle-online');
    Route::post('/vehicle/update', [Courier\DashboardController::class, 'updateVehicle'])->name('vehicle.update');
    Route::post('/offers/{offer}/accept', [Courier\DashboardController::class, 'acceptOffer'])->name('offers.accept');
    Route::post('/offers/{offer}/decline', [Courier\DashboardController::class, 'declineOffer'])->name('offers.decline');
    Route::post('/pickup/confirm', [Courier\DashboardController::class, 'confirmPickup'])->name('pickup.confirm');
    Route::post('/deliveries/{order}/complete', [Courier\DashboardController::class, 'completeDelivery'])->name('deliveries.complete');
    Route::post('/deliveries/{order}/fail', [Courier\DashboardController::class, 'failDelivery'])->name('deliveries.fail');
    Route::get('/earnings', [Courier\DashboardController::class, 'earnings'])->name('earnings');
    Route::post('/remittance/submit', [Courier\DashboardController::class, 'submitRemittance'])->name('remittance.submit');
    Route::post('/deliveries/{order}/status', [Courier\DashboardController::class, 'updateStatus'])->name('deliveries.update-status');
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
Route::get('/stores/{seller}', [StoreController::class, 'show'])->name('stores.show');

Route::middleware('role:buyer')->group(function () {
    // B5: Wishlist & Store Follow
    Route::get('/buyer/wishlist', [Buyer\WishlistController::class, 'index'])->name('buyer.wishlist');
    Route::post('/buyer/wishlist/{product}/toggle', [Buyer\WishlistController::class, 'toggle'])->name('buyer.wishlist.toggle');
    Route::post('/buyer/stores/{seller}/follow-toggle', [Buyer\WishlistController::class, 'toggleFollow'])->name('buyer.stores.follow-toggle');

    // B7: Vouchers
    Route::get('/buyer/vouchers', [Buyer\VoucherController::class, 'index'])->name('buyer.vouchers');
    Route::post('/buyer/vouchers/{voucher}/claim', [Buyer\VoucherController::class, 'claim'])->name('buyer.vouchers.claim');

    // B6: Cart
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    Route::patch('/cart/select-all', [CartController::class, 'selectAll'])->name('cart.select-all');
    Route::patch('/cart/select-store/{sellerId}', [CartController::class, 'selectStore'])->name('cart.select-store');

    // B8, B9: Checkout & Payment
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // B10, B11, B12, B13: Orders, Tracking, Cancellation, Returns, Reviews
    Route::get('/buyer/orders', [OrdersController::class, 'index'])->name('buyer.orders');
    Route::post('/buyer/orders/{order}/cancel', [OrdersController::class, 'cancelOrder'])->name('buyer.orders.cancel');
    Route::post('/buyer/orders/{order}/confirm-receipt', [OrdersController::class, 'confirmReceipt'])->name('buyer.orders.confirm-receipt');
    Route::post('/buyer/orders/{order}/return', [OrdersController::class, 'submitReturnRequest'])->name('buyer.orders.return');
    Route::post('/buyer/orders/{order}/review', [OrdersController::class, 'submitReview'])->name('buyer.orders.review');
    Route::post('/buyer/orders/{order}/feedback', [OrdersController::class, 'submitReview'])->name('buyer.orders.feedback');
    Route::post('/buyer/orders/{order}/dispute', [OrdersController::class, 'submitReturnRequest'])->name('buyer.orders.dispute');

    // B14: Buyer-Seller Chat
    Route::get('/buyer/chat', [Buyer\ChatController::class, 'index'])->name('buyer.chat.index');
    Route::get('/buyer/chat/{conversation}', [Buyer\ChatController::class, 'show'])->name('buyer.chat.show');
    Route::post('/buyer/chat/start', [Buyer\ChatController::class, 'startWithSeller'])->name('buyer.chat.start');
    Route::post('/buyer/chat/{conversation}/message', [Buyer\ChatController::class, 'sendMessage'])->name('buyer.chat.message');
});



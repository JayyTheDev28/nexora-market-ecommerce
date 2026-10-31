<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users table enhancements
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('valid_id_path');
            $table->string('pending_email')->nullable()->after('avatar_path');
            $table->string('pending_email_otp', 6)->nullable()->after('pending_email');
            $table->timestamp('pending_email_otp_expires_at')->nullable()->after('pending_email_otp');
            $table->string('pending_phone')->nullable()->after('pending_email_otp_expires_at');
            $table->string('pending_phone_otp', 6)->nullable()->after('pending_phone');
            $table->timestamp('pending_phone_otp_expires_at')->nullable()->after('pending_phone_otp');
        });

        // 2. Addresses table enhancements
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('region_code')->nullable()->after('user_id');
            $table->string('region_name')->nullable()->after('region_code');
            $table->string('recipient_name')->nullable()->after('region_name');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
            $table->string('postal_code', 10)->nullable()->after('house_number');
            $table->boolean('is_default')->default(false)->after('postal_code');
        });

        // 3. Products table enhancements (soft deletes, weight, purchase limits, flash sale, variant matrix)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight', 8, 2)->default(1.00)->after('stock');
            $table->unsignedInteger('max_purchase_limit')->nullable()->after('weight');
            $table->decimal('flash_sale_price', 10, 2)->nullable()->after('compare_price');
            $table->timestamp('flash_sale_ends_at')->nullable()->after('flash_sale_price');
            $table->json('variants_data')->nullable()->after('sizes');
            $table->softDeletes()->after('updated_at');
        });

        // 4. Vouchers table enhancements
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('voucher_scope', ['platform', 'store', 'shipping'])->default('store')->after('seller_id');
            $table->decimal('min_spend', 10, 2)->default(0)->after('value');
            $table->decimal('max_discount', 10, 2)->nullable()->after('min_spend');
            $table->unsignedInteger('usage_limit')->nullable()->after('max_discount');
            $table->boolean('is_active')->default(true)->after('usage_limit');
        });

        // 5. User Voucher Claims
        Schema::create('user_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'voucher_id']);
        });

        // 6. Wishlist table
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });

        // 7. Store Follows table
        Schema::create('store_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'seller_id']);
        });

        // 8. Orders table enhancements
        // Ensure status column can accommodate 'cancelled' and all ERP statuses
        DB::statement("ALTER TABLE orders MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'placed'");

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('buyer_id')->constrained('users')->nullOnDelete();
            $table->string('checkout_session_id')->nullable()->after('order_number');
            $table->json('address_snapshot')->nullable()->after('address_id');
            $table->string('payment_status', 30)->default('unpaid')->after('payment_method');
            $table->timestamp('payment_expires_at')->nullable()->after('payment_status');
            $table->timestamp('delivered_at')->nullable()->after('placed_at');
            $table->timestamp('completed_at')->nullable()->after('delivered_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            $table->decimal('shipping_weight', 8, 2)->default(1.00)->after('shipping_fee');
            $table->foreignId('shipping_voucher_id')->nullable()->after('discount')->constrained('vouchers')->nullOnDelete();
            $table->foreignId('store_voucher_id')->nullable()->after('shipping_voucher_id')->constrained('vouchers')->nullOnDelete();
            $table->foreignId('platform_voucher_id')->nullable()->after('store_voucher_id')->constrained('vouchers')->nullOnDelete();
        });

        // 9. Reviews enhancements
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->json('photos')->nullable()->after('comment');
            $table->unsignedTinyInteger('edited_count')->default(0)->after('photos');
        });

        // 10. Disputes / Returns enhancements
        Schema::table('disputes', function (Blueprint $table) {
            $table->json('media_paths')->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('media_paths');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'photos', 'edited_count']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropForeign(['shipping_voucher_id']);
            $table->dropForeign(['store_voucher_id']);
            $table->dropForeign(['platform_voucher_id']);
            $table->dropColumn([
                'seller_id',
                'checkout_session_id',
                'address_snapshot',
                'payment_status',
                'payment_expires_at',
                'delivered_at',
                'completed_at',
                'cancelled_at',
                'cancellation_reason',
                'shipping_weight',
                'shipping_voucher_id',
                'store_voucher_id',
                'platform_voucher_id',
            ]);
        });

        Schema::dropIfExists('store_follows');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('user_vouchers');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['voucher_scope', 'min_spend', 'max_discount', 'usage_limit', 'is_active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['weight', 'max_purchase_limit', 'flash_sale_price', 'flash_sale_ends_at', 'variants_data']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['region_code', 'region_name', 'recipient_name', 'recipient_phone', 'postal_code', 'is_default']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path',
                'pending_email',
                'pending_email_otp',
                'pending_email_otp_expires_at',
                'pending_phone',
                'pending_phone_otp',
                'pending_phone_otp_expires_at',
            ]);
        });
    }
};

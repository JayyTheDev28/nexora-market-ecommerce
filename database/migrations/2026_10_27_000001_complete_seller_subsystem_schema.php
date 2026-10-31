<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Seller Profiles expansion
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('category');
            $table->string('logo_path')->nullable()->after('description');
            $table->string('banner_path')->nullable()->after('logo_path');
            $table->string('contact_phone', 30)->nullable()->after('banner_path');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->boolean('is_vacation_mode')->default(false)->after('contact_email');
            $table->text('vacation_notice')->nullable()->after('is_vacation_mode');
        });

        // 2. Store-specific Categories (distinct from platform categories)
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('store_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('order_index')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['seller_id', 'slug']);
        });

        // 3. Products enhancements for store categories, SKU, dimensions, low-stock threshold
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('store_category_id')->nullable()->after('category_id')->constrained('store_categories')->nullOnDelete();
            $table->string('sku')->nullable()->after('slug');
            $table->string('dimensions')->nullable()->after('weight');
            $table->unsignedInteger('low_stock_threshold')->default(10)->after('stock');
        });

        // 4. Reviews enhancements for seller replies
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('seller_reply')->nullable()->after('comment');
            $table->timestamp('seller_replied_at')->nullable()->after('seller_reply');
        });

        // 5. Disputes / Returns enhancements for seller response workflow
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('seller_status', 30)->default('pending')->after('status');
            $table->text('seller_response')->nullable()->after('seller_status');
            $table->timestamp('seller_response_at')->nullable()->after('seller_response');
            $table->text('return_instructions')->nullable()->after('seller_response_at');
        });

        // 6. Seller Payout Requests
        Schema::create('seller_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payout_method', 50); // bank_transfer, gcash, maya
            $table->string('account_name');
            $table->string('account_number');
            $table->string('status', 30)->default('pending'); // pending, processed, rejected
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payout_requests');

        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['seller_status', 'seller_response', 'seller_response_at', 'return_instructions']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['seller_reply', 'seller_replied_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['store_category_id']);
            $table->dropColumn(['store_category_id', 'sku', 'dimensions', 'low_stock_threshold']);
        });

        Schema::dropIfExists('store_categories');

        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'logo_path',
                'banner_path',
                'contact_phone',
                'contact_email',
                'is_vacation_mode',
                'vacation_notice',
            ]);
        });
    }
};

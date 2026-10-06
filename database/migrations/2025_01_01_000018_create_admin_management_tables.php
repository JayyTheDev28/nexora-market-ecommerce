<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covers three admin responsibilities from the ERP spec:
     * "Post Announcements", "Update Platform Policies", and
     * "Issue Warnings or Suspend Seller Accounts for Violations".
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');

            // Which roles should see this announcement; null means everyone.
            $table->json('target_roles')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general'); // e.g. 'policy', 'commission'
            $table->timestamps();
        });

        Schema::create('seller_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('violation_type'); // e.g. 'category_mismatch', 'prohibited_item'
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_warnings');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('announcements');
    }
};

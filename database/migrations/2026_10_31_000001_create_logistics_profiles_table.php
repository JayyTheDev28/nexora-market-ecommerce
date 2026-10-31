<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('company_name')->unique();
            $table->string('sec_registration_no')->unique();
            $table->string('tin')->unique();
            $table->string('region');
            $table->string('province');
            $table->string('city_municipality');
            $table->string('barangay');
            $table->string('street_address');
            $table->string('postal_code', 20);
            $table->string('contact_person_first_name', 50);
            $table->string('contact_person_last_name', 50);
            $table->string('position');
            $table->string('email')->unique();
            $table->string('mobile_number')->unique();
            $table->json('service_areas');
            $table->string('sec_certificate_path')->nullable();
            $table->string('bir_2303_path')->nullable();
            $table->string('mayors_permit_path')->nullable();
            $table->string('valid_id_path')->nullable();
            $table->enum('status', ['unverified', 'pending_approval', 'approved', 'rejected'])->default('unverified');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_profiles');
    }
};


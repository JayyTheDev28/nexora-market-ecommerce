<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Both the PSGC code and the resolved name are stored, so we
            // don't need to re-hit the API just to display an address later.
            $table->string('province_code');
            $table->string('province_name');
            $table->string('municipality_code');
            $table->string('municipality_name');
            $table->string('barangay_code');
            $table->string('barangay_name');
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};

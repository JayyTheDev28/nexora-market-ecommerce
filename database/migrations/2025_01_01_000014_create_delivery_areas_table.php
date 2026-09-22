<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name'); // e.g. "Area A", or a municipality name
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_areas');
    }
};

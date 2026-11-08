<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'current_hub_id')) {
                $table->foreignId('current_hub_id')->nullable()->after('destination_hub_id')->constrained('hubs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'current_hub_id')) {
                $table->dropForeign(['current_hub_id']);
                $table->dropColumn('current_hub_id');
            }
        });
    }
};


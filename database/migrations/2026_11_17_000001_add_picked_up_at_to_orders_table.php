<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'picked_up_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('picked_up_at')->nullable()->after('placed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'picked_up_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('picked_up_at');
            });
        }
    }
};

<?php

use App\Models\DeliveryArea;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add SoftDeletes to addresses so deleting an address does not fail
        // with foreign key constraint errors when referenced by orders.
        if (Schema::hasTable('addresses') && ! Schema::hasColumn('addresses', 'deleted_at')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        // 2. Ensure Cavite Delivery Areas exist so legacy DeliveryArea checks
        // seamlessly recognize Cavite courier servicing areas.
        if (Schema::hasTable('delivery_areas')) {
            $caviteCompany = User::where('email', 'cavite.logistics@nexora.test')
                ->orWhere(function ($q) {
                    $q->whereIn('role', ['logistics', 'sorting_center'])
                      ->where('approval_status', 'approved');
                })
                ->first();

            $carrierId = $caviteCompany?->id;

            if ($carrierId) {
                $caviteAreas = [
                    'Cavite - City of Bacoor',
                    'Cavite - City of Dasmariñas',
                    'Cavite - City of Imus',
                    'Cavite - City of General Trias',
                    'Cavite - City of Tagaytay',
                    'Cavite - Silang',
                    'Cavite - Kawit',
                    'Cavite Province Servicing Area',
                ];

                foreach ($caviteAreas as $areaName) {
                    DeliveryArea::firstOrCreate(
                        ['name' => $areaName],
                        ['sorting_center_id' => $carrierId, 'courier_id' => null]
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'deleted_at')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};


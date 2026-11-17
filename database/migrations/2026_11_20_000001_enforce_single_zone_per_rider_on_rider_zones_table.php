<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rider_zones')) {
            return;
        }

        // Safely deduplicate any pre-existing multi-zone assignments before applying UNIQUE(rider_id)
        $duplicates = DB::table('rider_zones')
            ->select('rider_id')
            ->groupBy('rider_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('rider_id');

        foreach ($duplicates as $riderId) {
            $preferredHubId = DB::table('courier_profiles')->where('user_id', $riderId)->value('assigned_hub_id');
            $rows = DB::table('rider_zones')
                ->join('zones', 'zones.id', '=', 'rider_zones.zone_id')
                ->where('rider_zones.rider_id', $riderId)
                ->select('rider_zones.id', 'zones.hub_id')
                ->orderBy('rider_zones.id')
                ->get();

            $keep = $preferredHubId
                ? $rows->firstWhere('hub_id', $preferredHubId)
                : null;
            $keepId = ($keep ?? $rows->first())->id;

            DB::table('rider_zones')
                ->where('rider_id', $riderId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('rider_zones', function (Blueprint $table) {
            $table->unique('rider_id', 'rider_zones_rider_id_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rider_zones')) {
            return;
        }

        Schema::table('rider_zones', function (Blueprint $table) {
            $table->dropUnique('rider_zones_rider_id_unique');
        });
    }
};

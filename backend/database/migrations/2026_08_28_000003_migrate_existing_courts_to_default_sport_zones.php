<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Query builder only: the SportZone model was removed by 2026_10_20_000002, and
// 2026_08_14_000001 seeds a default venue, so this loop runs on every fresh database.
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('venues')->pluck('id') as $venueId) {
                $zoneId = DB::table('sport_zones')
                    ->where('venue_id', $venueId)
                    ->where('sport_type', 'badminton')
                    ->value('id')
                    ?? DB::table('sport_zones')->insertGetId([
                        'venue_id' => $venueId,
                        'sport_type' => 'badminton',
                        'name' => 'Khu Sân Cầu Lông',
                        'slot_duration' => 60,
                        'description' => 'Khu vực sân cầu lông tiêu chuẩn',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('courts')
                    ->where('venue_id', $venueId)
                    ->whereNull('sport_zone_id')
                    ->update(['sport_zone_id' => $zoneId]);
            }
        });
    }

    public function down(): void
    {
        DB::table('courts')->update(['sport_zone_id' => null]);
        DB::table('sport_zones')->delete();
    }
};

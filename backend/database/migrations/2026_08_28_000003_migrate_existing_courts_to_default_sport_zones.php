<?php

use App\Models\Court;
use App\Models\SportZone;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $venues = Venue::all();

            foreach ($venues as $venue) {
                $zone = SportZone::firstOrCreate(
                    [
                        'venue_id' => $venue->id,
                        'sport_type' => 'badminton',
                    ],
                    [
                        'name' => 'Khu Sân Cầu Lông',
                        'slot_duration' => 60,
                        'description' => 'Khu vực sân cầu lông tiêu chuẩn',
                        'is_active' => true,
                    ]
                );

                Court::where('venue_id', $venue->id)
                    ->whereNull('sport_zone_id')
                    ->update(['sport_zone_id' => $zone->id]);
            }
        });
    }

    public function down(): void
    {
        Court::query()->update(['sport_zone_id' => null]);
        SportZone::query()->delete();
    }
};

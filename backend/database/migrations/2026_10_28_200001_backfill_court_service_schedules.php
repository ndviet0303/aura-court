<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * UC-2.7 A-1, BR-33: courts no longer inherit the venue's operating hours. Every existing court without its own
 * service schedule gets an explicit copy of its venue's current hours, so courts that took bookings keep the same
 * service time. Courts of venues without hours stay without a schedule, as they were closed before too.
 */
return new class extends Migration
{
    public function up(): void
    {
        $venueHours = DB::table('venue_operating_schedules')->where('scope_key', 'venue')->get()->keyBy('venue_id');
        $scheduled = DB::table('venue_operating_schedules')->whereNotNull('court_id')->pluck('court_id')->all();
        $now = now();

        DB::table('courts')
            ->whereIn('venue_id', $venueHours->keys())
            ->whereNotIn('id', $scheduled)
            ->orderBy('id')
            ->get(['id', 'venue_id'])
            ->each(function (object $court) use ($venueHours, $now): void {
                $venue = $venueHours[$court->venue_id];
                DB::table('venue_operating_schedules')->insert([
                    'venue_id' => $court->venue_id,
                    'court_id' => $court->id,
                    'scope_key' => 'court:'.$court->id,
                    'timezone' => $venue->timezone,
                    'version' => 1,
                    'weekly_hours' => $venue->weekly_hours,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    // The copies cannot be told apart from schedules the owners saved later, so nothing is removed.
    public function down(): void {}
};

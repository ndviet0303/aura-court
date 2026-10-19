<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * remove-sport-zones, step 1 of 2. Additive, so it runs before the new code is deployed:
 * courts take the sport and slot length of their zone, and zone-only price rules become
 * venue + sport rules. 2026_10_20_000002 drops the zones once the new code is live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->string('sport_type')->default('badminton');
            $table->unsignedInteger('slot_duration')->default(60);
            $table->index(['venue_id', 'sport_type']);
        });

        Schema::table('price_rules', function (Blueprint $table) {
            $table->string('sport_type')->nullable();
        });

        DB::statement('UPDATE courts SET sport_type = z.sport_type, slot_duration = z.slot_duration
            FROM sport_zones z WHERE z.id = courts.sport_zone_id');

        // A zone rule whose venue differs from the zone's venue matches no court today; leave it for guard (b).
        DB::statement('UPDATE price_rules SET sport_type = z.sport_type, venue_id = z.venue_id
            FROM sport_zones z
            WHERE z.id = price_rules.sport_zone_id AND price_rules.court_id IS NULL
              AND (price_rules.venue_id IS NULL OR price_rules.venue_id = z.venue_id)');
    }

    public function down(): void
    {
        // Rows written by the new code have no zone; dropping the columns would reprice or reshape them silently.
        $orphans = DB::select("SELECT 'price_rules' AS t, id FROM price_rules WHERE sport_type IS NOT NULL AND sport_zone_id IS NULL
            UNION ALL SELECT 'courts', id FROM courts WHERE sport_zone_id IS NULL AND (sport_type <> 'badminton' OR slot_duration <> 60)");
        if ($orphans !== []) {
            throw new RuntimeException('Cannot drop the sport columns: attach these rows to a zone first ('
                .implode(', ', array_map(fn (object $row) => "{$row->t} {$row->id}", $orphans)).').');
        }

        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn('sport_type');
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->dropIndex(['venue_id', 'sport_type']);
            $table->dropColumn(['sport_type', 'slot_duration']);
        });
    }
};

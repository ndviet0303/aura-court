<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * remove-sport-zones, step 2 of 2. Runs after the code that reads courts.sport_type is live.
 * Each guard lists rows whose price, sport or slot length would change once zones are gone;
 * any hit aborts the migration (PostgreSQL DDL is transactional, so nothing is dropped).
 */
return new class extends Migration
{
    private const GUARDS = [
        'court rule names another zone than its court' => 'SELECT r.id FROM price_rules r JOIN courts c ON c.id = r.court_id
            WHERE r.sport_zone_id IS NOT NULL AND r.sport_zone_id IS DISTINCT FROM c.sport_zone_id',
        'zone rule not mapped to its venue and sport' => 'SELECT r.id FROM price_rules r JOIN sport_zones z ON z.id = r.sport_zone_id
            WHERE r.court_id IS NULL AND (r.sport_type IS DISTINCT FROM z.sport_type OR r.venue_id IS DISTINCT FROM z.venue_id)',
        'priced zone courts differ from venue + sport courts' => 'SELECT DISTINCT z.id FROM sport_zones z
            JOIN courts c ON c.venue_id = z.venue_id OR c.sport_zone_id = z.id
            WHERE EXISTS (SELECT 1 FROM price_rules r WHERE r.sport_zone_id = z.id AND r.court_id IS NULL)
              AND (c.sport_zone_id IS NOT DISTINCT FROM z.id) IS DISTINCT FROM (c.venue_id = z.venue_id AND c.sport_type = z.sport_type)',
        'court sport or slot length differs from its zone' => 'SELECT c.id FROM courts c JOIN sport_zones z ON z.id = c.sport_zone_id
            WHERE c.sport_type <> z.sport_type OR c.slot_duration <> z.slot_duration',
    ];

    public function up(): void
    {
        foreach (self::GUARDS as $reason => $sql) {
            $ids = array_map(fn (object $row) => $row->id, DB::select($sql));
            if ($ids !== []) {
                throw new RuntimeException("Cannot drop sport zones: {$reason} (ids: ".implode(', ', $ids).').');
            }
        }

        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_zone_id');
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_zone_id');
        });

        Schema::dropIfExists('sport_zones');
    }

    public function down(): void
    {
        $mixed = DB::select('SELECT venue_id, sport_type FROM courts GROUP BY venue_id, sport_type HAVING COUNT(DISTINCT slot_duration) > 1');
        if ($mixed !== []) {
            throw new RuntimeException('Cannot rebuild sport zones: courts of one venue and sport use different slot lengths.');
        }

        Schema::create('sport_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->json('name');
            $table->string('sport_type')->default('badminton');
            $table->unsignedInteger('slot_duration')->default(60);
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['venue_id', 'sport_type']);
        });

        foreach (['courts', 'price_rules'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('sport_zone_id')->nullable()->constrained('sport_zones')->nullOnDelete();
            });
            DB::statement("CREATE INDEX IF NOT EXISTS {$table}_sport_zone_id_index ON {$table} (sport_zone_id)");
        }

        // Zone names are not recoverable; the sport code stands in for them.
        DB::statement("INSERT INTO sport_zones (venue_id, name, sport_type, slot_duration, is_active, created_at, updated_at)
            SELECT p.venue_id, json_build_object('vi', p.sport_type, 'en', p.sport_type), p.sport_type,
                COALESCE((SELECT MIN(c.slot_duration) FROM courts c WHERE c.venue_id = p.venue_id AND c.sport_type = p.sport_type), 60),
                true, NOW(), NOW()
            FROM (SELECT venue_id, sport_type FROM courts
                  UNION SELECT venue_id, sport_type FROM price_rules WHERE sport_type IS NOT NULL) p");
        DB::statement('UPDATE courts SET sport_zone_id = z.id FROM sport_zones z
            WHERE z.venue_id = courts.venue_id AND z.sport_type = courts.sport_type');
        DB::statement('UPDATE price_rules SET sport_zone_id = z.id FROM sport_zones z
            WHERE z.venue_id = price_rules.venue_id AND z.sport_type = price_rules.sport_type');
    }
};

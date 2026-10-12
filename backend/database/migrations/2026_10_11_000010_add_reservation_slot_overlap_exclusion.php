<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store-level guard against overlapping active court time on one court, independent of
 * the application availability checks. Half-open bounds let back-to-back slots coexist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE reservation_slots ADD CONSTRAINT reservation_slots_no_overlap
            EXCLUDE USING gist (
                court_id WITH =,
                tsrange(starts_at, ends_at, '[)') WITH &&
            ) WHERE (status IN ('held', 'confirmed'))
            SQL);
    }

    public function down(): void
    {
        // The extension stays: other objects may depend on it.
        DB::statement('ALTER TABLE reservation_slots DROP CONSTRAINT reservation_slots_no_overlap');
    }
};

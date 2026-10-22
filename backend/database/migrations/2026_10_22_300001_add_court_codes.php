<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D9 (expand step): the court code replaces the court name as the display identifier. code_normalized is a
 * generated column the app never writes; it carries the case-insensitive uniqueness within a venue. The "und-x-icu"
 * collation makes lower() fold accented letters ("SÂN A" = "sân a") whatever the database locale is (design Risks).
 * Existing names become codes: vi, else en, trimmed; within a venue the smallest id keeps its code and the later
 * ones get " (2)", " (3)" in id order, cut so the whole code stays within 50 characters. name and slug only lose
 * NOT NULL here so the app can stop writing them; the contract step drops them.
 */
return new class extends Migration
{
    /** Same expression as the generated column; EloquentCourtRepository::codeTaken() compares with it too. */
    private const NORMALIZED = 'lower(btrim(code) COLLATE "und-x-icu")';

    private const SUFFIX_DUPLICATES = <<<'SQL'
        UPDATE courts c
        SET code = rtrim(left(d.code, 50 - length(' (' || d.n || ')'))) || ' (' || d.n || ')'
        FROM (
            SELECT id, code, row_number() OVER (PARTITION BY venue_id, %s ORDER BY id) AS n FROM courts
        ) d
        WHERE c.id = d.id AND d.n > 1
        SQL;

    public function up(): void
    {
        // The schema builder lets Larastan see the column; the empty default only lasts until the backfill below.
        Schema::table('courts', function (Blueprint $table) {
            $table->string('code', 50)->default('');
        });
        // A name with neither locale (never written by the app) still needs a code; its id keeps it unique.
        DB::statement(<<<'SQL'
            UPDATE courts SET code = COALESCE(
                NULLIF(btrim(left(btrim(name->>'vi'), 50)), ''),
                NULLIF(btrim(left(btrim(name->>'en'), 50)), ''),
                'Sân ' || id
            )
            SQL);

        // A suffixed code can meet an existing one ("A", "A", "A (2)"); another pass suffixes that pair as well.
        // Anything still colliding after five passes makes the unique constraint below fail the whole migration.
        for ($pass = 0; $pass < 5; $pass++) {
            if (DB::update(sprintf(self::SUFFIX_DUPLICATES, self::NORMALIZED)) === 0) {
                break;
            }
        }

        DB::statement('ALTER TABLE courts ALTER COLUMN code DROP DEFAULT');
        DB::statement('ALTER TABLE courts ADD COLUMN code_normalized text GENERATED ALWAYS AS ('.self::NORMALIZED.') STORED');
        DB::statement('ALTER TABLE courts ADD CONSTRAINT courts_venue_id_code_normalized_unique UNIQUE (venue_id, code_normalized)');
        DB::statement('ALTER TABLE courts ALTER COLUMN name DROP NOT NULL, ALTER COLUMN slug DROP NOT NULL');
    }

    public function down(): void
    {
        // Courts created after up() have no name; their code is the only name they have.
        DB::statement("UPDATE courts SET name = json_build_object('vi', code, 'en', code) WHERE name IS NULL");
        DB::statement('ALTER TABLE courts ALTER COLUMN name SET NOT NULL');
        DB::statement('ALTER TABLE courts DROP CONSTRAINT courts_venue_id_code_normalized_unique');
        DB::statement('ALTER TABLE courts DROP COLUMN code_normalized, DROP COLUMN code');
    }
};

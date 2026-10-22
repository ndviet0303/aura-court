<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venue price tables (design D1, D2). A table's prices live in its published versions; courts use tables
 * through dated links, at most one link of each kind per court at any instant. Interval minutes run on the
 * venue's local clock, [start, end) with 1440 as the end of the day (D15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 16);
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index('venue_id');
            // Target of the links' (price_table_id, kind) key, so a link always carries its table's kind.
            $table->unique(['id', 'kind']);
        });

        Schema::create('price_table_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_table_id')->constrained()->cascadeOnDelete();
            $table->dateTime('published_at');
            $table->dateTime('effective_at');
            $table->timestamps();
            $table->index(['price_table_id', 'effective_at']);
        });

        Schema::create('price_table_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_table_version_id')->constrained()->cascadeOnDelete();
            $table->json('weekdays')->nullable();
            $table->json('dates')->nullable();
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->unsignedInteger('price_per_hour');
            $table->timestamps();
            $table->index('price_table_version_id');
        });

        Schema::create('court_price_table_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_table_id');
            $table->string('kind', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
            $table->foreign(['price_table_id', 'kind'])->references(['id', 'kind'])->on('price_tables')->cascadeOnDelete();
            $table->index(['price_table_id', 'kind']);
        });

        DB::statement("ALTER TABLE price_tables ADD CONSTRAINT price_tables_kind_check CHECK (kind IN ('normal', 'holiday'))");
        DB::statement("ALTER TABLE price_tables ADD CONSTRAINT price_tables_status_check CHECK (status IN ('active', 'retired'))");
        // A requested effective time that is not in the future is stored as the publication time.
        DB::statement('ALTER TABLE price_table_versions ADD CONSTRAINT price_table_versions_effective_at_check CHECK (effective_at >= published_at)');
        DB::statement(<<<'SQL'
            ALTER TABLE price_table_intervals ADD CONSTRAINT price_table_intervals_range_check CHECK (
                start_minute < end_minute AND end_minute <= 1440 AND price_per_hour > 0
                AND (weekdays IS NULL) <> (dates IS NULL)
            )
            SQL);
        DB::statement('ALTER TABLE court_price_table_links ADD CONSTRAINT court_price_table_links_range_check CHECK (ends_at IS NULL OR ends_at > starts_at)');
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        // Half-open, so a link may start exactly where the previous one ends; a null end runs on.
        DB::statement(<<<'SQL'
            ALTER TABLE court_price_table_links ADD CONSTRAINT court_price_table_links_no_overlap
            EXCLUDE USING gist (
                court_id WITH =,
                kind WITH =,
                tsrange(starts_at, ends_at, '[)') WITH &&
            )
            SQL);
    }

    public function down(): void
    {
        // The extension stays: other objects depend on it.
        Schema::dropIfExists('court_price_table_links');
        Schema::dropIfExists('price_table_intervals');
        Schema::dropIfExists('price_table_versions');
        Schema::dropIfExists('price_tables');
    }
};

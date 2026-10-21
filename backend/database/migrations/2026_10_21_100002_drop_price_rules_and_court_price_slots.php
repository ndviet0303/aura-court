<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * align-module-03-pricing-promotions, Migration Plan step 1: the old price model goes once the previous migration has
 * moved its prices to price tables. down() rebuilds the tables empty; their rows come back only from the backup.
 *
 * Active old prices with no price table link anywhere mean the conversion never ran (its migrations row inserted by
 * hand, this file run alone with --path, or SQL taken from --pretend), so the guard aborts instead of dropping every
 * price. Guard and drops are one PostgreSQL statement, so the same SQL is safe when applied through psql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM court_price_table_links)
                    AND (EXISTS (SELECT 1 FROM price_rules WHERE is_active)
                        OR EXISTS (SELECT 1 FROM court_price_slots WHERE is_active)) THEN
                    RAISE EXCEPTION 'price_rules/court_price_slots still hold active prices but no court has a price table link; run 2026_10_21_100001_convert_price_rules_to_price_tables through artisan migrate first';
                END IF;
                DROP TABLE IF EXISTS court_price_slots;
                DROP TABLE IF EXISTS price_rules;
            END $$
            SQL);
    }

    public function down(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->index()->constrained('venues')->nullOnDelete();
            $table->foreignId('court_id')->nullable()->constrained('courts')->nullOnDelete();
            $table->string('sport_type')->nullable();
            $table->string('type', 24);
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->date('exception_date')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('priority')->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'type', 'is_active']);
            $table->index(['organization_id', 'exception_date']);
            $table->index(['court_id', 'weekday', 'starts_at']);
        });

        Schema::create('court_price_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedBigInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['court_id', 'weekday', 'starts_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews and disputes reference the reservation; booking_id stays as nullable history.
 * Rows of mapped legacy bookings are linked through legacy_booking_mappings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->unique()->constrained()->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE reviews ALTER COLUMN booking_id DROP NOT NULL');

        // dispute_cases.booking_id is already nullable.
        Schema::table('dispute_cases', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->index()->constrained()->nullOnDelete();
        });

        foreach (['reviews', 'dispute_cases'] as $table) {
            DB::statement("UPDATE {$table} t SET reservation_id = m.reservation_id FROM legacy_booking_mappings m WHERE m.legacy_booking_id = t.booking_id AND t.reservation_id IS NULL");
        }
    }

    public function down(): void
    {
        foreach (['reviews', 'dispute_cases'] as $table) {
            DB::statement("UPDATE {$table} t SET booking_id = m.legacy_booking_id FROM legacy_booking_mappings m WHERE m.reservation_id = t.reservation_id AND t.booking_id IS NULL");
        }
        // A review of a V2-only reservation has no booking to fall back to; refuse rather than delete it.
        if (DB::table('reviews')->whereNull('booking_id')->exists()) {
            throw new RuntimeException('Reviews without a legacy booking exist; rolling back would lose them.');
        }

        DB::statement('ALTER TABLE reviews ALTER COLUMN booking_id SET NOT NULL');
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });
        // Disputes keep their reservation reference in reference_type/reference_id.
        Schema::table('dispute_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};

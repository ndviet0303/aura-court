<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 06 D4: a no-show has no check-in time (BR-135), so it moves to its own columns; the readiness step and its
 * awaiting_inspection status are gone (D6). Attendance adjustments are append-only rows per booking (BR-136).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_attendances', function (Blueprint $table) {
            $table->dateTime('checked_in_at')->nullable()->change();
            $table->unsignedBigInteger('checked_in_by')->nullable()->change();
            $table->dateTime('no_show_at')->nullable();
            $table->foreignId('no_show_by')->nullable()->index()->constrained('users')->nullOnDelete();
        });
        DB::statement("UPDATE booking_attendances SET no_show_at = checked_in_at, no_show_by = checked_in_by, checked_in_at = NULL, checked_in_by = NULL WHERE status = 'no_show'");
        DB::statement('ALTER TABLE booking_attendances DROP CONSTRAINT booking_attendances_status_check');
        DB::statement("ALTER TABLE booking_attendances ADD CONSTRAINT booking_attendances_status_check CHECK (status IN ('checked_in', 'checked_out', 'no_show'))");

        Schema::create('booking_attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adjusted_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->dateTime('created_at');
            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_attendance_adjustments');
        DB::statement('ALTER TABLE booking_attendances DROP CONSTRAINT booking_attendances_status_check');
        DB::statement("ALTER TABLE booking_attendances ADD CONSTRAINT booking_attendances_status_check CHECK (status IN ('awaiting_inspection', 'checked_in', 'checked_out', 'no_show'))");
        DB::statement("UPDATE booking_attendances SET checked_in_at = no_show_at, checked_in_by = no_show_by WHERE status = 'no_show'");
        Schema::table('booking_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('no_show_by');
            $table->dropColumn('no_show_at');
            $table->dateTime('checked_in_at')->nullable(false)->change();
            $table->unsignedBigInteger('checked_in_by')->nullable(false)->change();
        });
    }
};

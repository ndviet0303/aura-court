<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One handling case per affected booking and source (design D7, D8; spec "Affected bookings from
 * operational changes"). source_id has no foreign key: a deleted schedule exception keeps its cases.
 * source_starts_at / source_ends_at snapshot the source window for the case list, in UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affected_reservation_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->dateTime('source_starts_at')->nullable();
            $table->dateTime('source_ends_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('contact_channel', 8)->nullable();
            $table->text('note')->nullable();
            $table->text('refund_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();
            // Named: the generated name is 70 characters and PostgreSQL would truncate it to 63.
            $table->unique(['reservation_id', 'source_type', 'source_id'], 'affected_reservation_cases_source_unique');
            $table->index(['venue_id', 'status']);
            $table->index(['court_id', 'status']);
            $table->index('handled_by');
        });

        DB::statement("ALTER TABLE affected_reservation_cases ADD CONSTRAINT affected_reservation_cases_status_check CHECK (status IN ('pending', 'contacting', 'unreachable', 'moved', 'time_changed', 'cancelled', 'kept'))");
        DB::statement("ALTER TABLE affected_reservation_cases ADD CONSTRAINT affected_reservation_cases_source_type_check CHECK (source_type IN ('operational_stop', 'venue_suspension', 'venue_schedule', 'schedule_exception', 'court_schedule'))");
        DB::statement("ALTER TABLE affected_reservation_cases ADD CONSTRAINT affected_reservation_cases_contact_channel_check CHECK (contact_channel IN ('phone', 'zalo', 'chat'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('affected_reservation_cases');
    }
};

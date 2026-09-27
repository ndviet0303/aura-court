<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_posts', function (Blueprint $table) {
            $table->dateTime('registration_deadline')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('entitlement_id')->nullable()->constrained('reservation_entitlements')->nullOnDelete();
            $table->boolean('approval_required')->default(false);
            $table->dateTime('roster_closed_at')->nullable();
        });

        Schema::table('match_participations', function (Blueprint $table) {
            $table->string('attendance_status', 32)->nullable();
            $table->foreignId('attendance_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('attendance_recorded_at')->nullable();
            $table->text('attendance_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('match_participations', function (Blueprint $table) {
            $table->dropForeign(['attendance_recorded_by']);
            $table->dropColumn([
                'attendance_status',
                'attendance_recorded_by',
                'attendance_recorded_at',
                'attendance_notes',
            ]);
        });

        Schema::table('match_posts', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['entitlement_id']);
            $table->dropColumn([
                'registration_deadline',
                'reservation_id',
                'entitlement_id',
                'approval_required',
                'roster_closed_at',
            ]);
        });
    }
};

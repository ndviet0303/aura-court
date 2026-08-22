<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_drill_runs', function (Blueprint $table) {
            $table->id();
            $table->string('drill_type', 48);
            $table->string('status', 24)->index();
            $table->json('evidence')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('v1_adoption_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('legacy_booking_count')->default(0);
            $table->unsignedInteger('v2_reservation_count')->default(0);
            $table->unsignedInteger('legacy_active_count')->default(0);
            $table->unsignedInteger('legacy_user_count')->default(0);
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->unique(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v1_adoption_snapshots');
        Schema::dropIfExists('operational_drill_runs');
    }
};

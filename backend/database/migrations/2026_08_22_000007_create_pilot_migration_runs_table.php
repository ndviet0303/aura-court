<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 24);
            $table->string('status', 24)->index();
            $table->json('organization_ids');
            $table->unsignedInteger('source_booking_count')->default(0);
            $table->unsignedInteger('target_reservation_count')->default(0);
            $table->unsignedBigInteger('source_amount')->default(0);
            $table->unsignedBigInteger('target_amount')->default(0);
            $table->char('source_checksum', 64)->nullable();
            $table->char('target_checksum', 64)->nullable();
            $table->json('results')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_migration_runs');
    }
};

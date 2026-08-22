<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_gate_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('phase', 32);
            $table->string('decision', 16)->index();
            $table->json('organization_ids');
            $table->json('reconciliation_run_ids')->nullable();
            $table->boolean('rollback_verified')->default(false);
            $table->json('evidence')->nullable();
            $table->string('reviewer', 160)->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('reviewed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_gate_reviews');
    }
};

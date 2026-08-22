<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceptance_runs', function (Blueprint $table) {
            $table->id();
            $table->string('phase', 32);
            $table->string('status', 24)->index();
            $table->json('suites');
            $table->json('results')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptance_runs');
    }
};

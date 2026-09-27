<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_operating_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope_key', 32)->default('venue');
            $table->string('timezone', 64);
            $table->unsignedInteger('version')->default(1);
            $table->json('weekly_hours');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['venue_id', 'scope_key']);
        });

        Schema::create('venue_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope_key', 32)->default('venue');
            $table->date('exception_date');
            $table->string('name', 120);
            $table->string('type', 32);
            $table->json('intervals')->nullable();
            $table->string('timezone', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['venue_id', 'scope_key', 'exception_date']);
            $table->index(['venue_id', 'exception_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_schedule_exceptions');
        Schema::dropIfExists('venue_operating_schedules');
    }
};

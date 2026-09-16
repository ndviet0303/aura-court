<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_posts', function (Blueprint $table) {
            $table->boolean('is_owner_hosted')->default(false)->index();
            $table->foreignId('court_id')->nullable()->constrained('courts')->nullOnDelete();
            $table->string('category', 64)->nullable()->index();
            $table->json('skill_levels')->nullable();
        });

        Schema::create('match_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_post_id')->unique()->constrained('match_posts')->cascadeOnDelete();
            $table->unsignedSmallInteger('actual_headcount')->nullable();
            $table->unsignedInteger('court_duration_minutes')->nullable();
            $table->unsignedBigInteger('court_hourly_rate')->nullable();
            $table->unsignedInteger('shuttlecock_count')->nullable();
            $table->unsignedBigInteger('shuttlecock_unit_price')->nullable();
            $table->unsignedBigInteger('court_subtotal')->nullable();
            $table->unsignedBigInteger('shuttlecock_subtotal')->nullable();
            $table->unsignedBigInteger('total_amount')->nullable();
            $table->unsignedBigInteger('per_person_amount')->nullable();
            $table->boolean('is_approximate')->default(false);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_bills');

        Schema::table('match_posts', function (Blueprint $table) {
            $table->dropForeign(['court_id']);
            $table->dropColumn(['is_owner_hosted', 'court_id', 'category', 'skill_levels']);
        });
    }
};

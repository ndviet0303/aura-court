<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('skill_level')->default(1);
            $table->string('city', 120)->nullable()->index();
            $table->decimal('approximate_latitude', 8, 5)->nullable();
            $table->decimal('approximate_longitude', 8, 5)->nullable();
            $table->unsignedTinyInteger('location_precision_km')->default(5);
            $table->json('availability')->nullable();
            $table->json('preferred_positions')->nullable();
            $table->unsignedSmallInteger('reputation_score')->default(60);
            $table->string('visibility', 24)->default('organization')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('match_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('visibility', 24)->default('public')->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('city', 120)->nullable()->index();
            $table->decimal('approximate_latitude', 8, 5)->nullable();
            $table->decimal('approximate_longitude', 8, 5)->nullable();
            $table->unsignedTinyInteger('min_skill')->nullable();
            $table->unsignedTinyInteger('max_skill')->nullable();
            $table->unsignedTinyInteger('required_participants');
            $table->unsignedTinyInteger('joined_count')->default(1);
            $table->string('status', 24)->default('open')->index();
            $table->json('availability')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'visibility', 'starts_at']);
        });

        Schema::create('match_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['match_post_id', 'invitee_id']);
        });

        Schema::create('match_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('joined')->index();
            $table->dateTime('joined_at');
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['match_post_id', 'user_id']);
        });

        Schema::create('qualifying_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->smallInteger('score_delta');
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('qualifies_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'source_type', 'source_id', 'type']);
            $table->index(['organization_id', 'user_id', 'qualifies_at']);
        });

        Schema::create('reputation_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qualifying_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('filed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('open')->index();
            $table->string('decision', 32)->nullable();
            $table->text('reason');
            $table->text('resolution')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('target_type', 120);
            $table->unsignedBigInteger('target_id');
            $table->string('action', 40);
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'target_type', 'target_id']);
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('format', 32);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedBigInteger('version')->default(1);
            $table->dateTime('starts_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seed');
            $table->string('status', 24)->default('registered')->index();
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();
            $table->unique(['tournament_id', 'user_id']);
            $table->unique(['tournament_id', 'seed']);
        });

        Schema::create('tournament_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->unsignedInteger('position');
            $table->foreignId('participant_one_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->foreignId('participant_two_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->json('score')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tournament_id', 'round', 'position']);
        });

        Schema::create('tournament_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('tournament_participants')->cascadeOnDelete();
            $table->unsignedInteger('played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->integer('points')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tournament_id', 'participant_id']);
        });

        Schema::create('tournament_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('aggregate_version');
            $table->string('event_type', 80);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tournament_id', 'aggregate_version']);
            $table->index(['organization_id', 'tournament_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_events');
        Schema::dropIfExists('tournament_standings');
        Schema::dropIfExists('tournament_fixtures');
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('moderation_records');
        Schema::dropIfExists('reputation_disputes');
        Schema::dropIfExists('qualifying_activities');
        Schema::dropIfExists('match_participations');
        Schema::dropIfExists('match_invitations');
        Schema::dropIfExists('match_posts');
        Schema::dropIfExists('player_profiles');
    }
};

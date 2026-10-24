<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 07 part C (D16, D18). The old tournament tables keyed participants by account and are dropped with their
 * demo data. A tournament is one sport and one event (D17), public or internal (BR-148); an entry is a unit whose
 * members need no account (BR-150); public tournaments reserve court time (BR-152) and internal ones play in the
 * renter's confirmed bookings. Standings are computed from the fixtures, so they have no table.
 */
return new class extends Migration
{
    private const DROPPED = ['tournament_events', 'tournament_standings', 'tournament_fixtures', 'tournament_grants', 'tournament_participants', 'tournaments'];

    public function up(): void
    {
        foreach (self::DROPPED as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('name', 180);
            $table->string('sport', 16);
            $table->string('entry_type', 16);
            $table->unsignedSmallInteger('team_main')->nullable();
            $table->unsignedSmallInteger('team_reserve')->nullable();
            $table->unsignedSmallInteger('max_entries')->nullable();
            $table->string('format', 16);
            $table->unsignedSmallInteger('group_count')->nullable();
            $table->unsignedSmallInteger('advance_per_group')->nullable();
            $table->unsignedSmallInteger('games_to_win')->nullable();
            $table->json('points');
            $table->json('tiebreakers');
            $table->text('rules');
            $table->text('eligibility')->nullable();
            $table->json('athlete_fields');
            $table->text('forfeit_rule');
            $table->text('prizes')->nullable();
            $table->unsignedInteger('fee')->default(0);
            $table->dateTime('registration_deadline')->nullable();
            $table->dateTime('payment_deadline')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 16)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('stopped_at')->nullable();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('stop_reason')->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'starts_at']);
            $table->index('venue_id');
            $table->index('organization_id');
            $table->index('created_by');
            $table->index('stopped_by');
        });

        Schema::create('tournament_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
            $table->index('tournament_id');
            $table->index('granted_by');
        });

        Schema::create('tournament_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 16);
            $table->text('reject_reason')->nullable();
            $table->string('group', 4)->nullable();
            $table->unsignedSmallInteger('seed')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['tournament_id', 'status']);
            $table->index('user_id');
        });

        Schema::create('tournament_entry_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('tournament_entries')->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->date('birth_date');
            $table->json('extra')->nullable();
            $table->string('role', 16)->default('main');
            $table->unsignedSmallInteger('position');
            $table->index('entry_id');
        });

        Schema::create('tournament_court_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['court_id', 'starts_at', 'ends_at']);
            $table->index('tournament_id');
            $table->index('created_by');
        });

        Schema::create('tournament_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index('tournament_id');
        });

        Schema::create('tournament_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 16);
            $table->string('group', 4)->default('');
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('position');
            $table->foreignId('entry_one_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->foreignId('entry_two_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->foreignId('winner_entry_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->foreignId('forfeit_entry_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('score')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();
            $table->unique(['tournament_id', 'stage', 'group', 'round', 'position']);
            $table->index(['court_id', 'starts_at']);
            $table->index('entry_one_id');
            $table->index('entry_two_id');
            $table->index('winner_entry_id');
            $table->index('forfeit_entry_id');
        });

        Schema::create('tournament_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('payload');
            $table->dateTime('created_at');
            $table->index(['tournament_id', 'created_at']);
            $table->index('actor_id');
        });

        DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_type_check CHECK (type IN ('public', 'internal'))");
        DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_sport_check CHECK (sport IN ('badminton', 'football'))");
        DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_status_check CHECK (status IN ('draft', 'open', 'roster_locked', 'drawn', 'in_progress', 'finished', 'stopped'))");
        DB::statement("ALTER TABLE tournament_entries ADD CONSTRAINT tournament_entries_status_check CHECK (status IN ('submitted', 'approved', 'rejected', 'paid', 'withdrawn', 'confirmed'))");
        DB::statement("ALTER TABLE tournament_fixtures ADD CONSTRAINT tournament_fixtures_status_check CHECK (status IN ('pending', 'bye', 'completed'))");
        DB::statement("ALTER TABLE tournament_grants ADD CONSTRAINT tournament_grants_role_check CHECK (role IN ('official', 'representative'))");
        // One live application per account and tournament; a rejected or withdrawn one may be followed by a new one.
        DB::statement("CREATE UNIQUE INDEX tournament_entries_active_user_unique ON tournament_entries (tournament_id, user_id) WHERE user_id IS NOT NULL AND status NOT IN ('rejected', 'withdrawn')");
        DB::statement('CREATE UNIQUE INDEX tournament_grants_active_unique ON tournament_grants (tournament_id, user_id, role) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        foreach (['tournament_events', 'tournament_fixtures', 'tournament_rentals', 'tournament_court_allocations', 'tournament_entry_members', 'tournament_entries', 'tournament_grants', 'tournaments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 07 part B. D13: matchmaking sessions, reputation, friendships and reactions are dropped with their data.
 * D12: one platform-wide feed; a post is a share or a matchmaking post with its play details. D14: reports and
 * moderation belong to the platform admin, so neither carries an organization. D15: reviews belong to the venue.
 */
return new class extends Migration
{
    private const DROPPED = ['match_bills', 'match_invitations', 'match_participations', 'match_posts', 'reputation_disputes', 'qualifying_activities', 'player_profiles', 'friendships', 'feed_reactions'];

    public function up(): void
    {
        foreach (self::DROPPED as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('feed_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('audience');
            $table->string('type', 16)->default('share');
            $table->string('sport', 32)->nullable();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address', 255)->nullable();
            $table->string('court_code', 50)->nullable();
            $table->dateTime('play_at')->nullable();
            $table->json('images')->nullable();
            $table->index(['is_hidden', 'type', 'created_at']);
        });

        Schema::table('feed_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->index(['reportable_type', 'reportable_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('moderation_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->index(['target_type', 'target_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('images')->nullable();
            $table->text('reply_body')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->softDeletes();
        });
        DB::statement('UPDATE reviews r SET venue_id = c.venue_id FROM courts c WHERE c.id = r.court_id');
        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['venue_id', 'is_published', 'created_at']);
        });
    }

    /** The dropped tables and their data are not restored; the reshaped tables get their old columns back. */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['venue_id', 'is_published', 'created_at']);
            $table->dropConstrainedForeignId('venue_id');
            $table->dropConstrainedForeignId('replied_by');
            $table->dropColumn(['images', 'reply_body', 'replied_at', 'deleted_at']);
        });

        foreach (['moderation_records', 'feed_reports'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('feed_posts', function (Blueprint $table) {
            $table->dropIndex(['is_hidden', 'type', 'created_at']);
            $table->dropConstrainedForeignId('venue_id');
            $table->dropColumn(['type', 'sport', 'address', 'court_code', 'play_at', 'images']);
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('audience', 32)->default('organization');
        });
    }
};

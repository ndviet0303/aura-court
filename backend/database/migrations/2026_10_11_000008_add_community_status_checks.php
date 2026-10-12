<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status CHECK constraints for matchmaking, splits, resale, social, disputes and tournaments.
 * Allowed values are what the code writes and reads plus what existing data holds.
 * Adding a status means a migration that drops and re-adds the constraint.
 */
return new class extends Migration
{
    private const CHECKS = [
        ['match_posts', 'status', ['active', 'cancelled', 'completed', 'ended', 'full', 'open']],
        ['match_participations', 'status', ['joined', 'pending_approval', 'rejected', 'withdrawn']],
        ['match_participations', 'attendance_status', ['absent', 'late', 'present', 'withdrawn']],
        ['match_invitations', 'status', ['accepted', 'declined', 'pending']],
        ['match_bills', 'status', ['draft', 'finalized']],
        ['split_groups', 'status', ['completed', 'confirmed', 'expired', 'pending', 'settled']],
        ['split_shares', 'status', ['expired', 'paid', 'pending']],
        ['marketplace_listings', 'status', ['checkout', 'listed', 'sold', 'withdrawn']],
        ['entitlement_transfers', 'status', ['compensated', 'completed', 'pending']],
        ['friendships', 'status', ['accepted', 'declined', 'pending']],
        ['feed_reports', 'status', ['dismissed', 'pending', 'resolved']],
        ['dispute_cases', 'status', ['awaiting_evidence', 'awaiting_response', 'closed', 'dismissed', 'investigating', 'pending', 'reopened', 'resolved']],
        ['reputation_disputes', 'status', ['open', 'resolved']],
        ['tournaments', 'status', ['draft', 'finalized', 'in_progress', 'open', 'ready']],
        ['tournament_fixtures', 'status', ['bye', 'completed', 'live', 'pending', 'scheduled']],
        ['tournament_participants', 'status', ['absent', 'checked_in', 'present', 'registered', 'rejected', 'withdrawn']],
    ];

    public function up(): void
    {
        foreach (self::CHECKS as [$table, $column, $values]) {
            $allowed = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$allowed}))");
        }
    }

    public function down(): void
    {
        foreach (self::CHECKS as [$table, $column]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$column}_check");
        }
    }
};

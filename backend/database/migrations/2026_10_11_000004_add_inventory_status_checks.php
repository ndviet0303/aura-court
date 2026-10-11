<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status CHECK constraints for court inventory and bookings.
 * Allowed values are what the code writes and reads plus what existing data holds.
 * Adding a status means a migration that drops and re-adds the constraint.
 */
return new class extends Migration
{
    private const CHECKS = [
        ['reservations', 'status', ['cancelled', 'completed', 'confirmed', 'disputed', 'expired', 'held', 'in_use', 'legacy']],
        ['reservation_slots', 'status', ['active', 'cancelled', 'confirmed', 'expired', 'held', 'in_use', 'legacy']],
        ['reservation_entitlements', 'status', ['active', 'cancelled', 'expired', 'held', 'legacy', 'transferred']],
        ['bookings', 'status', ['cancelled', 'completed', 'confirmed', 'expired', 'in_use', 'no_show', 'pending_payment', 'refund_pending', 'refunded']],
        // 'booked' rows on bt-server are rewritten to 'confirmed' by a reviewed data fix before this runs.
        ['booking_slots', 'status', ['cancelled', 'confirmed', 'expired', 'held']],
        ['recurring_series', 'status', ['active', 'cancelled', 'confirmed', 'delinquent', 'draft', 'overdue', 'pending']],
        ['recurring_occurrences', 'status', ['confirmed', 'held', 'released']],
        ['waitlist_entries', 'status', ['cancelled', 'notified', 'waiting']],
        ['price_quotes', 'status', ['accepted', 'open', 'pending_payment']],
        ['booking_attendances', 'status', ['awaiting_inspection', 'checked_in', 'checked_out', 'no_show']],
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

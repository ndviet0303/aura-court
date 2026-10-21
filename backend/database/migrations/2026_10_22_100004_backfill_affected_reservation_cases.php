<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opens one pending case per booking affected (BR-38, design D7) by each open stop and each active
 * suspension, so bookings on objects stopped before this change can be handled by the D8 commands
 * (task 4.2, design risk "Đơn tương lai trên đối tượng bị pause hoặc đóng khi migrate").
 * Idempotent: running up() twice opens each case once, through the case's unique key.
 */
return new class extends Migration
{
    /** BR-38: held, confirmed or in use, and not cancelled, expired, completed or no-show. */
    private const AFFECTED_RESERVATION = <<<'SQL'
        r.status IN ('held', 'confirmed', 'in_use')
        AND COALESCE(r.metadata->>'operational_status', '') NOT IN ('completed', 'no_show')
        SQL;

    private const INSERT_CASE = 'INSERT INTO affected_reservation_cases
        (reservation_id, venue_id, court_id, source_type, source_id, source_starts_at, source_ends_at, status, created_at, updated_at)';

    /** The slot statuses that hold inventory, as SlotSelectionPolicy counts them. */
    private const LIVE_SLOT = "SELECT 1 FROM reservation_slots rs WHERE rs.reservation_id = r.id AND rs.status IN ('held', 'confirmed')";

    public function up(): void
    {
        $now = now();
        $nowUtc = now('UTC');

        // D5 for a stop with no recorded end: a booking overlaps when a live slot ends after both the stop start and now.
        // A venue-level stop (court_id null) reaches every court of the venue.
        DB::statement(
            self::INSERT_CASE."
            SELECT r.id, c.venue_id, r.court_id, 'operational_stop', s.id, s.starts_at, s.expected_end_at, 'pending', ?, ?
            FROM operational_stops s
            JOIN courts c ON c.id = s.court_id OR (s.court_id IS NULL AND c.venue_id = s.venue_id)
            JOIN reservations r ON r.court_id = c.id
            WHERE s.ended_at IS NULL AND ".self::AFFECTED_RESERVATION.'
              AND EXISTS ('.self::LIVE_SLOT.' AND rs.ends_at > GREATEST(s.starts_at, ?))
            ON CONFLICT (reservation_id, source_type, source_id) DO NOTHING',
            [$now, $now, $nowUtc],
        );

        // An active suspension has started and has no end, so every booking of the venue that ends after now overlaps it.
        DB::statement(
            self::INSERT_CASE."
            SELECT r.id, c.venue_id, r.court_id, 'venue_suspension', vs.id, vs.suspended_at, NULL, 'pending', ?, ?
            FROM venue_suspensions vs
            JOIN courts c ON c.venue_id = vs.venue_id
            JOIN reservations r ON r.court_id = c.id
            WHERE vs.lifted_at IS NULL AND ".self::AFFECTED_RESERVATION.'
              AND EXISTS ('.self::LIVE_SLOT.' AND rs.ends_at > ?)
            ON CONFLICT (reservation_id, source_type, source_id) DO NOTHING',
            [$now, $now, $nowUtc],
        );
    }

    public function down(): void
    {
        // Backfilled cases cannot be told apart from cases opened later; the previous migration's down drops the table.
    }
};

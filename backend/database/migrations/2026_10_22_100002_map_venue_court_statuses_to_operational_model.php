<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data step of the four-status model (design D4, D5, Migration Plan step 4).
 * Maps the old venue statuses, turns each suspended venue into an active venue_suspensions row,
 * derives courts.status from is_active, opens a stop for every paused or closed object and copies
 * court_blocks into ended maintenance stops. Stop and suspension times are UTC.
 */
return new class extends Migration
{
    private const VENUE_STATUS_MAP = [
        'draft' => 'inactive',
        'pending_publication' => 'inactive',
        'revision_required' => 'inactive',
        'published' => 'active',
        'active' => 'active',
        'temporarily_closed' => 'paused',
        'closed' => 'closed',
        'archived' => 'closed',
    ];

    /** Lossy reverse of design "Rollback"; an active suspension wins over it. */
    private const REVERSE_STATUS_MAP = [
        'inactive' => 'draft',
        'active' => 'published',
        'paused' => 'temporarily_closed',
    ];

    private const DEFAULT_REASON = 'Chuyển đổi dữ liệu';

    private const MAINTENANCE_REASON = 'Bảo trì';

    /** The latest audit row that suspended venue v: its actor, time and the status before it. */
    private const LAST_SUSPENSION_AUDIT = <<<'SQL'
        SELECT al.user_id, al.created_at, al.metadata->>'previous_status' AS previous_status
        FROM audit_logs al
        WHERE al.auditable_type = 'App\Models\Venue' AND al.auditable_id = v.id
          AND al.action = 'venue.lifecycle.transitioned' AND al.metadata->>'new_status' = 'suspended'
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT 1
        SQL;

    public function up(): void
    {
        $now = now();
        $nowUtc = now('UTC');

        // Audit and updated_at hold app-timezone wall clocks; suspended_at is UTC.
        DB::statement(
            'INSERT INTO venue_suspensions (venue_id, reason, suspended_by, suspended_at, created_at, updated_at)
            SELECT v.id, COALESCE(NULLIF(btrim(v.status_reason), \'\'), ?), a.user_id,
                   (COALESCE(a.created_at, v.updated_at, ?) AT TIME ZONE ?) AT TIME ZONE \'UTC\', ?, ?
            FROM venues v
            LEFT JOIN LATERAL ('.self::LAST_SUSPENSION_AUDIT.') a ON true
            WHERE v.status = \'suspended\'',
            [self::DEFAULT_REASON, $now, config('app.timezone'), $now, $now],
        );

        // No audit row, or one whose previous status is suspended too, leaves the safe default paused.
        DB::statement(
            'UPDATE venues v SET status = COALESCE((SELECT '.$this->caseSql('a.previous_status', self::VENUE_STATUS_MAP).'
                FROM ('.self::LAST_SUSPENSION_AUDIT.') a), \'paused\')
            WHERE v.status = \'suspended\''
        );

        $renamed = array_filter(self::VENUE_STATUS_MAP, fn (string $to, string $from) => $to !== $from, ARRAY_FILTER_USE_BOTH);
        DB::statement(
            'UPDATE venues SET status = '.$this->caseSql('status', $renamed).' WHERE status IN ('.$this->quoted(array_keys($renamed)).')'
        );

        DB::statement("UPDATE courts SET status = CASE WHEN is_active THEN 'active' ELSE 'paused' END");

        DB::statement(
            "INSERT INTO operational_stops (venue_id, court_id, kind, reason, starts_at, created_at, updated_at)
            SELECT id, NULL, CASE status WHEN 'closed' THEN 'closure' ELSE 'business' END,
                   COALESCE(NULLIF(btrim(status_reason), ''), ?), ?, ?, ?
            FROM venues WHERE status IN ('paused', 'closed')",
            [self::DEFAULT_REASON, $nowUtc, $now, $now],
        );

        DB::statement(
            "INSERT INTO operational_stops (venue_id, court_id, kind, reason, starts_at, created_at, updated_at)
            SELECT venue_id, id, 'business', ?, ?, ?, ? FROM courts WHERE status = 'paused'",
            [self::DEFAULT_REASON, $nowUtc, $now, $now],
        );

        // An old block keeps blocking exactly its old window: its end becomes the recorded end.
        DB::statement(
            "INSERT INTO operational_stops (venue_id, court_id, kind, reason, starts_at, expected_end_at, ended_at, created_at, updated_at)
            SELECT c.venue_id, cb.court_id, 'maintenance', COALESCE(NULLIF(btrim(cb.reason), ''), ?),
                   cb.starts_at, cb.ends_at, cb.ends_at, COALESCE(cb.created_at, ?), ?
            FROM court_blocks cb JOIN courts c ON c.id = cb.court_id",
            [self::MAINTENANCE_REASON, $now, $now],
        );
    }

    public function down(): void
    {
        DB::statement(
            "UPDATE venues v SET status = 'suspended', status_reason = s.reason
            FROM venue_suspensions s WHERE s.venue_id = v.id AND s.lifted_at IS NULL"
        );
        DB::statement(
            'UPDATE venues SET status = '.$this->caseSql('status', self::REVERSE_STATUS_MAP).' WHERE status IN ('.$this->quoted(array_keys(self::REVERSE_STATUS_MAP)).')'
        );
        DB::statement("UPDATE courts SET is_active = (status = 'active')");
        // The old code reads venues.is_active raw; the new code never writes it and new venues take its default true.
        DB::statement("UPDATE venues SET is_active = (status IN ('published', 'active'))");

        // Every stop and suspension is folded into the old status columns above; the old schema has no other place for them.
        DB::statement('DELETE FROM operational_stops');
        DB::statement('DELETE FROM venue_suspensions');
    }

    /** @param  array<string, string>  $map */
    private function caseSql(string $expression, array $map): string
    {
        $whens = array_map(fn (string $from, string $to) => "WHEN '{$from}' THEN '{$to}'", array_keys($map), $map);

        return "CASE {$expression} ".implode(' ', $whens).' END';
    }

    /** @param  list<string>  $values */
    private function quoted(array $values): string
    {
        return implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
    }
};

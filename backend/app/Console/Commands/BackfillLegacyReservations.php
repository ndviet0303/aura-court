<?php

namespace App\Console\Commands;

use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLegacyReservations extends Command
{
    protected $signature = 'v2:backfill-legacy-bookings 
        {--organization= : Limit the backfill to one organization id}
        {--dry-run : Perform dry-run without writing changes}
        {--audit : Perform audit only and report orphans and collisions}';

    protected $description = 'Backfill immutable legacy bookings into the v2 compatibility projection with audit and dry-run reporting';

    public function handle(LegacyBookingBackfillService $backfill): int
    {
        $organizationId = $this->option('organization');
        $orgId = $organizationId !== null ? (int) $organizationId : null;
        // A mirror mapped before it kept its booking's created_at would move in date-filtered reports (design I13).
        $drift = 'legacy_booking_mappings m JOIN bookings b ON b.id = m.legacy_booking_id WHERE r.id = m.reservation_id AND r.created_at <> b.created_at'.($orgId !== null ? ' AND m.organization_id = ?' : '');
        $driftBindings = $orgId !== null ? [$orgId] : [];
        $createdAtDrift = (int) DB::scalar("SELECT count(*) FROM reservations r, {$drift}", $driftBindings);

        if ($this->option('audit')) {
            $result = $backfill->audit($orgId) + ['created_at_drift' => $createdAtDrift];
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $backfill->backfill($orgId, $dryRun) + ['created_at_drift' => $createdAtDrift];
        if (! $dryRun) {
            // Same link as the reservation_id migration: catches rows written through booking_id after it ran.
            foreach (['reviews_relinked' => 'reviews', 'disputes_relinked' => 'dispute_cases'] as $key => $table) {
                $result[$key] = DB::update("UPDATE {$table} t SET reservation_id = m.reservation_id FROM legacy_booking_mappings m WHERE m.legacy_booking_id = t.booking_id AND t.reservation_id IS NULL");
            }
            $result['created_at_aligned'] = DB::update("UPDATE reservations r SET created_at = b.created_at FROM {$drift}", $driftBindings);
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($dryRun) {
            return self::SUCCESS;
        }

        return ($result['valid'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}

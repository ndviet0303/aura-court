<?php

namespace App\Console\Commands;

use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Console\Command;

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

        if ($this->option('audit')) {
            $result = $backfill->audit($orgId);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $backfill->backfill($orgId, $dryRun);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($dryRun) {
            return self::SUCCESS;
        }

        return ($result['valid'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}

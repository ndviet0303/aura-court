<?php

namespace App\Console\Commands;

use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Console\Command;

class BackfillLegacyReservations extends Command
{
    protected $signature = 'v2:backfill-legacy-bookings {--organization= : Limit the backfill to one organization id}';

    protected $description = 'Backfill immutable legacy bookings into the v2 compatibility projection';

    public function handle(LegacyBookingBackfillService $backfill): int
    {
        $organizationId = $this->option('organization');
        $result = $backfill->backfill($organizationId !== null ? (int) $organizationId : null);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}

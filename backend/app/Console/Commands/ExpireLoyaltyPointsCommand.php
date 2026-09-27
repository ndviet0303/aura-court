<?php

namespace App\Console\Commands;

use App\Services\Loyalty\LoyaltyPointService;
use Illuminate\Console\Command;

class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points {--organization= : Restrict the sweep to a single organization id}';

    protected $description = 'Materialise append-only reversals for loyalty point lots whose expiry has passed';

    public function handle(LoyaltyPointService $service): int
    {
        $organizationId = $this->option('organization') !== null
            ? (int) $this->option('organization')
            : null;

        $written = $service->expireDuePoints($organizationId);
        $this->info("Wrote {$written} loyalty point expiry reversal(s).");

        return self::SUCCESS;
    }
}

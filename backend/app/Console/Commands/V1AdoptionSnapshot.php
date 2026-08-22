<?php

namespace App\Console\Commands;

use App\Services\V1AdoptionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class V1AdoptionSnapshot extends Command
{
    protected $signature = 'v1:adoption-snapshot {--days=30}';
    protected $description = 'Capture v1/v2 adoption evidence for the compatibility window';
    public function handle(V1AdoptionService $service): int { $end = CarbonImmutable::now(); $snapshot = $service->capture($end->subDays((int) $this->option('days')), $end); $this->line(json_encode($snapshot->toArray(), JSON_PRETTY_PRINT)); return self::SUCCESS; }
}

<?php

namespace App\Console\Commands;

use App\Services\Infrastructure\HardeningAcceptanceService;
use Illuminate\Console\Command;

class RunHardeningAcceptance extends Command
{
    protected $signature = 'hardening:acceptance';

    protected $description = 'Run final API, frontend, build, and accessibility-baseline acceptance suites';

    public function handle(HardeningAcceptanceService $service): int
    {
        $run = $service->run();
        $this->line(json_encode($run->toArray(), JSON_PRETTY_PRINT));

        return $run->status === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}

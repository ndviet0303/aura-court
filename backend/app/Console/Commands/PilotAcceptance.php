<?php

namespace App\Console\Commands;

use App\Services\Infrastructure\PilotAcceptanceService;
use Illuminate\Console\Command;

class PilotAcceptance extends Command
{
    protected $signature = 'pilot:acceptance {--include-load : Run the k6 peak/concurrency suite} {--include-recovery : Run the PostgreSQL backup/restore rehearsal}';

    protected $description = 'Execute and record Phase 1 pilot acceptance suites';

    public function handle(PilotAcceptanceService $acceptance): int
    {
        $run = $acceptance->run((bool) $this->option('include-load'), (bool) $this->option('include-recovery'));
        $this->line(json_encode($run->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $run->status === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}

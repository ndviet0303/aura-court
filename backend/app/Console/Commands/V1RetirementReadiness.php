<?php

namespace App\Console\Commands;

use App\Services\Reservation\V1AdoptionService;
use Illuminate\Console\Command;

class V1RetirementReadiness extends Command
{
    protected $signature = 'v1:retirement-readiness {--approved-change= : Relative path under openspec/changes for a separate approved retirement change}';

    protected $description = 'Block v1 retirement until adoption evidence and a separate approved change exist';

    public function handle(V1AdoptionService $service): int
    {
        $result = $service->retirementReadiness($this->option('approved-change'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}

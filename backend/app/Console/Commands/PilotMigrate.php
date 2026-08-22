<?php

namespace App\Console\Commands;

use App\Services\PilotMigrationService;
use Illuminate\Console\Command;

class PilotMigrate extends Command
{
    protected $signature = 'v2:pilot-migrate {--organizations= : Comma-separated organization IDs} {--rehearse : Validate without creating new mappings}';

    protected $description = 'Rehearse or apply the controlled five-organization Phase 1 migration';

    public function handle(PilotMigrationService $migrations): int
    {
        $value = (string) ($this->option('organizations') ?: implode(',', config('features.pilot_organizations', [])));
        $ids = array_values(array_filter(array_map('intval', explode(',', $value))));
        $run = $migrations->run($ids, (bool) $this->option('rehearse'));
        $this->line(json_encode($run->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $run->status === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\OperationalDrillService;
use Illuminate\Console\Command;

class RunOperationalDrill extends Command
{
    protected $signature = 'hardening:drill {type : peak-load|delayed-webhook|redis-restart|database-failover|queue-lag|realtime-degradation}';
    protected $description = 'Run or record a final operational resilience drill';
    public function handle(OperationalDrillService $service): int { $run = $service->run((string) $this->argument('type')); $this->line(json_encode($run->toArray(), JSON_PRETTY_PRINT)); return in_array($run->status, ['passed','manual'], true) ? self::SUCCESS : self::FAILURE; }
}

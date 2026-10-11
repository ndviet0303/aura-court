<?php

namespace App\Console\Commands;

use App\Services\Infrastructure\ObservabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class MonitorQueueLag extends Command
{
    protected $signature = 'monitor:queue-lag {--queue=default}';

    protected $description = 'Record queue depth and alert when asynchronous work is delayed';

    public function handle(ObservabilityService $observability): int
    {
        $queue = (string) $this->option('queue');
        $size = (int) Queue::size($queue);
        $observability->metric('queue.depth', $size, ['queue' => $queue]);
        if ($size >= (int) config('observability.queue_lag_alert', 30)) {
            $observability->alert('queue_lag', ['queue' => $queue, 'depth' => $size]);
        }
        $this->line("{$queue}: {$size}");

        return self::SUCCESS;
    }
}

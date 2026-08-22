<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RealtimeServer extends Command
{
    protected $signature = 'realtime:work {--channel=tenant-events : Redis pub/sub channel}';

    protected $description = 'Consume committed tenant events from managed Redis for the stateless realtime process';

    public function handle(): int
    {
        $channel = (string) $this->option('channel');
        $this->info("Listening for committed realtime events on [{$channel}].");
        Redis::connection('default')->subscribe([$channel], function (string $message): void {
            $this->line($message);
        });

        return self::SUCCESS;
    }
}

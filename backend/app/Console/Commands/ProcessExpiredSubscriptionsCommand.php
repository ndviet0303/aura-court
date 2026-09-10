<?php

namespace App\Console\Commands;

use App\Domain\Subscription\Actions\ProcessExpiredSubscriptions;
use Illuminate\Console\Command;

class ProcessExpiredSubscriptionsCommand extends Command
{
    protected $signature = 'platform:process-expired-subscriptions';
    protected $description = 'Scan and process expired platform subscriptions';

    public function handle(ProcessExpiredSubscriptions $action): int
    {
        $count = $action->execute();
        $this->info("Processed {$count} expired subscription(s).");

        return self::SUCCESS;
    }
}

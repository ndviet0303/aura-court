<?php

namespace App\Console\Commands;

use App\Jobs\RunDailyReconciliation;
use App\Models\Organization;
use Illuminate\Console\Command;

class ReconcileFinance extends Command
{
    protected $signature = 'finance:reconcile {--organization= : Run reconciliation for one organization}';

    protected $description = 'Reconcile provider activity, journals, settlements, and payouts';

    public function handle(): int
    {
        $query = Organization::query()->where('status', 'active');
        if ($this->option('organization')) {
            $query->whereKey((int) $this->option('organization'));
        }
        $query->pluck('id')->each(fn (int $id) => RunDailyReconciliation::dispatchSync($id));
        $this->info('Finance reconciliation completed.');

        return self::SUCCESS;
    }
}

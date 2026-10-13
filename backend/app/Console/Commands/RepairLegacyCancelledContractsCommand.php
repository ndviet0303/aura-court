<?php

namespace App\Console\Commands;

use App\Domain\Admin\Actions\RepairLegacyCancelledContracts;
use App\Models\User;
use Illuminate\Console\Command;

class RepairLegacyCancelledContractsCommand extends Command
{
    protected $signature = 'contracts:repair-legacy-cancel
        {--actor= : Id of the platform admin recorded as approver of the refund obligations}
        {--apply : Commit the repair; without it every contract is rolled back (dry run)}';

    protected $description = 'Release the court time, open invoices and payment attempts left by contracts the pre-fix admin path cancelled';

    public function handle(RepairLegacyCancelledContracts $repair): int
    {
        $actorId = (string) $this->option('actor');
        $actor = ctype_digit($actorId) ? User::query()->find((int) $actorId) : null;
        if (! $actor?->isPlatformAdmin()) {
            $this->error('--actor must be the id of a platform admin.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $rows = $repair->execute($actor, $apply);
        $this->table(
            ['Series', 'Organization', 'Status before', 'Sessions released', 'Invoices voided', 'Refund obligations', 'Refund amount', 'Started sessions to review'],
            array_map(fn (array $row) => [
                $row['series_id'], $row['organization_id'], $row['status'], $row['released'], $row['voided'], $row['refunds'], $row['refund_amount'], $row['past_live'],
            ], $rows)
        );
        $this->info(($apply ? 'Repaired ' : 'Dry run, rolled back: would repair ').count($rows).' contract(s).');

        return self::SUCCESS;
    }
}

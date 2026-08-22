<?php

namespace App\Console\Commands;

use App\Services\MoneyFlowUatService;
use Illuminate\Console\Command;

class RecordMoneyFlowUat extends Command
{
    protected $signature = 'pilot:money-flow-uat {--organizations= : Comma-separated pilot organization IDs} {--sandbox-verified : Confirm every approved PSP sandbox capability passed} {--rollback-verified : Confirm rollback rehearsal passed} {--reviewer= : Accountable reviewer} {--evidence= : JSON evidence object}';

    protected $description = 'Record sandbox money-flow UAT and the Phase 2 entry decision';

    public function handle(MoneyFlowUatService $uat): int
    {
        $value = (string) ($this->option('organizations') ?: implode(',', config('features.pilot_organizations', [])));
        $ids = array_values(array_filter(array_map('intval', explode(',', $value))));
        $evidence = $this->option('evidence') ? json_decode((string) $this->option('evidence'), true, flags: JSON_THROW_ON_ERROR) : [];
        $review = $uat->record($ids, (bool) $this->option('sandbox-verified'), (bool) $this->option('rollback-verified'), $evidence, $this->option('reviewer'));
        $this->line(json_encode($review->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $review->decision === 'go' ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Infrastructure\PhaseGateReviewService;
use Illuminate\Console\Command;

class ReviewPhaseGate extends Command
{
    protected $signature = 'pilot:phase-gate {phase=phase-2} {--organizations= : Comma-separated pilot organization IDs} {--rollback-verified : Confirm the rollback rehearsal passed} {--reviewer= : Accountable reviewer} {--evidence= : JSON evidence object}';

    protected $description = 'Run reconciliation and record a controlled phase-entry go/no-go decision';

    public function handle(PhaseGateReviewService $gates): int
    {
        $value = (string) ($this->option('organizations') ?: implode(',', config('features.pilot_organizations', [])));
        $ids = array_values(array_filter(array_map('intval', explode(',', $value))));
        $evidence = $this->option('evidence') ? json_decode((string) $this->option('evidence'), true, flags: JSON_THROW_ON_ERROR) : [];
        $review = $gates->review((string) $this->argument('phase'), $ids, (bool) $this->option('rollback-verified'), $evidence, $this->option('reviewer'));
        $this->line(json_encode($review->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $review->decision === 'go' ? self::SUCCESS : self::FAILURE;
    }
}

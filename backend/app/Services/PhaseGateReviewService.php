<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PhaseGateReview;
use App\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;

class PhaseGateReviewService
{
    public function __construct(private readonly ReconciliationService $reconciliation) {}

    public function review(string $phase, array $organizationIds, bool $rollbackVerified, array $evidence = [], ?string $reviewer = null): PhaseGateReview
    {
        $organizationIds = array_values(array_unique(array_map('intval', $organizationIds)));
        abort_if($organizationIds === [], 422, 'Pilot organizations are required.');
        $runs = [];
        $decision = 'go';
        $failure = null;
        foreach ($organizationIds as $organizationId) {
            $organization = Organization::findOrFail($organizationId);
            $run = $this->reconciliation->run($organization);
            $runs[] = $run->id;
            if ($run->status !== 'matched') {
                $decision = 'no-go';
                $failure = 'Reconciliation mismatch detected for organization '.$organizationId;
            }
        }
        if (! $rollbackVerified) {
            $decision = 'no-go';
            $failure = $failure ?: 'Rollback rehearsal evidence is required.';
        }

        return PhaseGateReview::create([
            'phase' => $phase,
            'decision' => $decision,
            'organization_ids' => $organizationIds,
            'reconciliation_run_ids' => $runs,
            'rollback_verified' => $rollbackVerified,
            'evidence' => $evidence,
            'reviewer' => $reviewer,
            'failure_reason' => $failure,
            'reviewed_at' => now(),
        ]);
    }
}

<?php

namespace App\Services\Infrastructure;

use App\Models\PhaseGateReview;

class MoneyFlowUatService
{
    public function __construct(private readonly PhaseGateReviewService $gates) {}

    public function record(array $organizationIds, bool $sandboxVerified, bool $rollbackVerified, array $evidence = [], ?string $reviewer = null): PhaseGateReview
    {
        $required = ['collection', 'verified_webhook', 'refund', 'beneficiary_kyc', 'payout_submission', 'payout_status', 'idempotent_retry'];
        $capabilities = [];
        foreach ($required as $capability) {
            $capabilities[$capability] = $sandboxVerified ? 'verified' : 'blocked';
        }
        $evidence = ['sandbox_capabilities' => $capabilities] + $evidence;
        return $this->gates->review('phase-2-entry', $organizationIds, $rollbackVerified && $sandboxVerified, $evidence, $reviewer);
    }
}

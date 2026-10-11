<?php

namespace App\Services\Finance;

use App\Contracts\Payment\PayoutInstruction;
use App\Models\Beneficiary;
use App\Models\FinanceException;
use App\Models\Payout;
use App\Models\Settlement;
use App\Services\Payment\ProviderOperationService;

class PayoutService
{
    public function __construct(
        private readonly ProviderOperationService $providers,
        private readonly SettlementService $settlements,
    ) {}

    public function createDraft(Settlement $settlement, Beneficiary $beneficiary): Payout
    {
        $settlement = $this->settlements->refreshEligibility($settlement);
        abort_unless($settlement->status === 'eligible' && $settlement->eligible_at?->isPast(), 409, 'Settlement is not yet eligible for payout.');
        abort_unless($settlement->tenant_payable >= (int) config('finance.minimum_payout', 500000), 422, 'Settlement is below the minimum payout threshold.');
        abort_unless($beneficiary->organization_id === $settlement->organization_id && $beneficiary->status === 'verified', 422, 'A verified beneficiary for this organization is required.');

        return Payout::firstOrCreate([
            'organization_id' => $settlement->organization_id,
            'idempotency_key' => "settlement:{$settlement->id}:payout",
        ], [
            'settlement_id' => $settlement->id,
            'beneficiary_id' => $beneficiary->id,
            'amount' => $settlement->tenant_payable,
            'currency' => $settlement->currency,
            'status' => 'draft',
            'settlement_snapshot' => $settlement->snapshot,
        ]);
    }

    public function approve(Payout $payout): Payout
    {
        abort_unless($payout->status === 'draft', 409, 'Only draft payouts can be approved.');
        abort_unless($payout->amount >= (int) config('finance.minimum_payout', 500000), 422, 'Payout is below the minimum threshold.');
        abort_unless($payout->beneficiary?->status === 'verified', 422, 'Beneficiary verification is required.');
        $payout->update(['status' => 'approved', 'approved_at' => now()]);

        return $payout->fresh(['settlement', 'beneficiary']);
    }

    public function submit(Payout $payout): Payout
    {
        abort_unless($payout->status === 'approved', 409, 'Only approved payouts can be submitted.');
        $provider = $payout->beneficiary->provider;
        $response = $this->providers->submitPayout(new PayoutInstruction(
            reference: "payout-{$payout->id}",
            beneficiaryId: (string) ($payout->beneficiary->provider_beneficiary_id ?: $payout->beneficiary->id),
            amount: $payout->amount,
            currency: $payout->currency,
            description: "Aura Court settlement {$payout->settlement_id}",
            idempotencyKey: $payout->idempotency_key,
            metadata: ['payout_id' => $payout->id, 'organization_id' => $payout->organization_id],
        ), $payout->organization_id);
        $payout->update([
            'provider' => $provider,
            'provider_payout_id' => $response->providerPayoutId,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $payout->fresh(['settlement', 'beneficiary']);
    }

    public function syncStatus(Payout $payout): Payout
    {
        abort_unless($payout->provider_payout_id, 409, 'Payout has not been submitted.');
        $status = $this->providers->provider($payout->provider)->queryPayoutStatus($payout->provider_payout_id);
        $normalized = strtolower($status->status);
        $attributes = ['status' => $normalized];
        if ($normalized === 'paid') {
            $attributes['paid_at'] = now();
        } elseif ($normalized === 'failed') {
            $attributes['failed_at'] = now();
            $attributes['failure_reason'] = $status->failureMessage ?: $status->failureCode;
            FinanceException::firstOrCreate([
                'organization_id' => $payout->organization_id,
                'type' => 'payout_failed',
                'reference' => (string) $payout->id,
                'status' => 'open',
            ], [
                'severity' => 'high',
                'amount' => $payout->amount,
                'description' => $attributes['failure_reason'] ?: 'Provider payout failed.',
                'links' => ['payout_id' => $payout->id],
                'detected_at' => now(),
                'due_at' => now()->addMinutes(15),
            ]);
        } elseif ($normalized === 'reversed') {
            $attributes['reversed_at'] = now();
            FinanceException::firstOrCreate([
                'organization_id' => $payout->organization_id,
                'type' => 'payout_reversed',
                'reference' => (string) $payout->id,
                'status' => 'open',
            ], [
                'severity' => 'critical',
                'amount' => $payout->amount,
                'description' => $status->failureMessage ?: "Provider payout {$payout->id} was reversed.",
                'links' => ['payout_id' => $payout->id],
                'detected_at' => now(),
                'due_at' => now()->addMinutes(15),
            ]);
        }
        $payout->update($attributes);

        return $payout->fresh(['settlement', 'beneficiary']);
    }
}

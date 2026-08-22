<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Journal;
use App\Models\Settlement;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function snapshot(Organization $organization, CarbonInterface $periodStart, CarbonInterface $periodEnd): Settlement
    {
        return DB::transaction(function () use ($organization, $periodStart, $periodEnd): Settlement {
            $existing = Settlement::query()->where('organization_id', $organization->id)->whereDate('period_start', $periodStart)->whereDate('period_end', $periodEnd)->lockForUpdate()->first();
            if ($existing?->snapshotted_at) {
                return $existing;
            }

            $journals = Journal::query()->where('organization_id', $organization->id)->whereBetween('posted_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])->get();
            $gross = $journals->filter(fn (Journal $journal) => in_array($journal->event_type, ['reservation.payment', 'booking.payment', 'provider.collection'], true))->sum('total_credit');
            $refunds = $journals->where('event_type', 'refund')->sum('total_debit');
            $commission = $journals->sum(fn (Journal $journal) => $journal->entries->where('side', 'credit')->where('account_id', $this->accountId($organization->id, 'platform_commission'))->sum('amount'));
            $providerFees = $journals->sum(fn (Journal $journal) => $journal->entries->where('side', 'credit')->where('account_id', $this->accountId($organization->id, 'provider_fee_expense'))->sum('amount'));
            $tenantPayable = $this->tenantPayable($organization->id, $journals);
            $eligibleAt = CarbonImmutable::parse($periodEnd)->endOfDay()->addDays((int) config('finance.settlement_delay_days', 2));
            $snapshot = [
                'journal_ids' => $journals->modelKeys(),
                'gross_amount' => (int) $gross,
                'refund_amount' => (int) $refunds,
                'commission_amount' => (int) $commission,
                'provider_fee_amount' => (int) $providerFees,
                'tenant_payable' => (int) $tenantPayable,
                'captured_at' => now()->toIso8601String(),
            ];

            return Settlement::updateOrCreate([
                'organization_id' => $organization->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ], [
                'eligible_at' => $eligibleAt,
                'gross_amount' => $gross,
                'refund_amount' => $refunds,
                'provider_fee_amount' => $providerFees,
                'commission_amount' => $commission,
                'tenant_payable' => $tenantPayable,
                'currency' => config('finance.currency', 'VND'),
                'status' => $eligibleAt->isPast() ? 'eligible' : 'pending',
                'snapshot' => $snapshot,
                'snapshotted_at' => now(),
            ]);
        });
    }

    public function refreshEligibility(Settlement $settlement): Settlement
    {
        if ($settlement->status === 'pending' && $settlement->eligible_at?->isPast()) {
            $settlement->update(['status' => 'eligible']);
        }
        return $settlement->fresh();
    }

    private function tenantPayable(int $organizationId, $journals): int
    {
        $accountId = $this->accountId($organizationId, 'tenant_payable');
        return (int) $journals->sum(fn (Journal $journal) => $journal->entries->where('account_id', $accountId)->sum(fn ($entry) => $entry->side === 'credit' ? $entry->amount : -$entry->amount));
    }

    private function accountId(int $organizationId, string $code): int
    {
        return (int) Account::query()->where('organization_id', $organizationId)->where('code', $code)->value('id');
    }
}

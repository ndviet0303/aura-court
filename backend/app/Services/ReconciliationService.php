<?php

namespace App\Services;

use App\Models\FinanceException;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Payout;
use App\Models\ProviderEvent;
use App\Models\ReconciliationRun;
use App\Models\Settlement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function run(Organization $organization, ?CarbonInterface $runDate = null): ReconciliationRun
    {
        $date = ($runDate ?: now())->toDateString();
        return DB::transaction(function () use ($organization, $date): ReconciliationRun {
            $existing = ReconciliationRun::query()->where('organization_id', $organization->id)->whereDate('run_date', $date)->lockForUpdate()->first();
            if ($existing?->completed_at) {
                return $existing;
            }
            $run = $existing ?: ReconciliationRun::create([
                'organization_id' => $organization->id,
                'run_date' => $date,
                'status' => 'running',
                'started_at' => now(),
            ]);

            $from = now()->parse($date)->startOfDay();
            $to = now()->parse($date)->endOfDay();
            $providerTotal = (int) ProviderEvent::query()->where('organization_id', $organization->id)->whereIn('status', ['PAID', 'COMPLETED', 'SUCCEEDED', 'SUCCESS', 'paid', 'completed', 'succeeded', 'success'])->whereBetween('verified_at', [$from, $to])->sum('amount');
            $journalTotal = (int) Journal::query()->where('organization_id', $organization->id)->whereIn('event_type', ['provider.collection', 'reservation.payment', 'booking.payment'])->whereBetween('posted_at', [$from, $to])->sum('total_credit');
            $settlementTotal = (int) Settlement::query()->where('organization_id', $organization->id)->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])->sum('tenant_payable');
            $payoutTotal = (int) Payout::query()->where('organization_id', $organization->id)->whereBetween('created_at', [$from, $to])->whereIn('status', ['submitted', 'paid', 'failed', 'reversed'])->sum('amount');
            $mismatch = abs($providerTotal - $journalTotal);
            $details = [
                'provider_vs_journal' => $providerTotal - $journalTotal,
                'journal_total' => $journalTotal,
                'settlement_total' => $settlementTotal,
                'payout_total' => $payoutTotal,
                'checked_at' => now()->toIso8601String(),
            ];
            $run->update([
                'status' => $mismatch === 0 ? 'matched' : 'mismatch',
                'provider_total' => $providerTotal,
                'journal_total' => $journalTotal,
                'settlement_total' => $settlementTotal,
                'payout_total' => $payoutTotal,
                'mismatch_amount' => $mismatch,
                'details' => $details,
                'completed_at' => now(),
            ]);

            if ($mismatch !== 0) {
                app(ObservabilityService::class)->alert('payout_mismatch', ['amount' => $mismatch, 'organization_id' => $organization->id, 'run_id' => $run->id]);
                FinanceException::firstOrCreate([
                    'organization_id' => $organization->id,
                    'type' => 'reconciliation_mismatch',
                    'reference' => "reconciliation:{$date}",
                    'status' => 'open',
                ], [
                    'severity' => 'high',
                    'amount' => $mismatch,
                    'description' => 'Provider, journal, settlement, or payout totals do not reconcile.',
                    'links' => ['reconciliation_run_id' => $run->id],
                    'metadata' => $details,
                    'detected_at' => now(),
                    'due_at' => now()->addMinutes(15),
                ]);
            }

            return $run->fresh();
        });
    }

    public function resolve(FinanceException $exception, int $userId, string $resolution): FinanceException
    {
        abort_unless($exception->status === 'open', 409, 'Finance exception is already resolved.');
        $exception->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $userId, 'resolution' => $resolution]);
        return $exception->fresh();
    }
}

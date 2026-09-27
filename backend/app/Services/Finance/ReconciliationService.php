<?php

namespace App\Services\Finance;

use App\Services\Infrastructure\ObservabilityService;

use App\Models\FinanceException;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\Payout;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformProviderReceipt;
use App\Models\ProviderEvent;
use App\Models\ReconciliationRun;
use App\Models\Settlement;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function run(Organization $organization, ?CarbonInterface $runDate = null): ReconciliationRun
    {
        $date = ($runDate ?: now())->toDateString();
        return DB::transaction(function () use ($organization, $date): ReconciliationRun {
            $existing = ReconciliationRun::query()->where('organization_id', $organization->id)->whereDate('run_date', $date)->lockForUpdate()->first();
            $run = $existing ?: ReconciliationRun::create([
                'organization_id' => $organization->id,
                'run_date' => $date,
                'status' => 'running',
                'started_at' => now(),
            ]);

            $from = Carbon::parse($date)->startOfDay();
            $to = Carbon::parse($date)->endOfDay();

            // 1. Direct Owner Receipts & Allocations
            $allocations = OwnerPaymentAllocation::query()
                ->where('organization_id', $organization->id)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('allocated_at', [$from, $to])
                      ->orWhereBetween('created_at', [$from, $to]);
                })
                ->with(['attempt', 'receipt'])
                ->get();

            $ownerReceipts = OwnerPaymentReceipt::query()
                ->where('organization_id', $organization->id)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('received_at', [$from, $to])
                      ->orWhereBetween('event_at', [$from, $to])
                      ->orWhereBetween('created_at', [$from, $to]);
                })
                ->with(['allocation', 'attempt'])
                ->get();

            // 2. Verified Provider Events (for this org / connections)
            $providerEvents = ProviderEvent::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', ['PAID', 'COMPLETED', 'SUCCEEDED', 'SUCCESS', 'paid', 'completed', 'succeeded', 'success'])
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('verified_at', [$from, $to])
                      ->orWhereBetween('created_at', [$from, $to]);
                })
                ->get();

            // 3. Compensating Owner Refunds
            $refunds = OwnerRefundObligation::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', ['acknowledged', 'reported', 'owner_reported', 'approved'])
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('recipient_acknowledged_at', [$from, $to])
                      ->orWhereBetween('owner_reported_at', [$from, $to])
                      ->orWhereBetween('approved_at', [$from, $to])
                      ->orWhereBetween('requested_at', [$from, $to])
                      ->orWhereBetween('created_at', [$from, $to]);
                })
                ->get();

            // 4. Platform Subscriptions (Platform entity)
            $platformAttempts = PlatformPaymentAttempt::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'succeeded')
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('paid_at', [$from, $to])
                      ->orWhereBetween('created_at', [$from, $to]);
                })
                ->with('receipt')
                ->get();

            $platformReceipts = PlatformProviderReceipt::query()
                ->whereHas('paymentAttempt', fn ($q) => $q->where('organization_id', $organization->id))
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('verified_at', [$from, $to])
                      ->orWhereBetween('created_at', [$from, $to]);
                })
                ->with('paymentAttempt')
                ->get();

            // 5. Legacy (Wallet/Custody, Settlements, Payouts, Journals)
            $journals = Journal::query()
                ->where('organization_id', $organization->id)
                ->whereBetween('posted_at', [$from, $to])
                ->get();

            $settlements = Settlement::query()
                ->where('organization_id', $organization->id)
                ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
                ->get();

            $payouts = Payout::query()
                ->where('organization_id', $organization->id)
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('status', ['submitted', 'paid', 'failed', 'reversed', 'completed'])
                ->get();

            // Helper: Classify purpose into standardized financial source categories
            $classifyPurpose = function (?string $purpose): string {
                $p = strtolower(trim((string) $purpose));
                return match (true) {
                    str_contains($p, 'recur') || str_contains($p, 'invoice') => 'recurring_invoices',
                    str_contains($p, 'split') => 'split_shares',
                    str_contains($p, 'transfer') => 'transfers',
                    str_contains($p, 'pos') => 'pos_orders',
                    str_contains($p, 'book') || str_contains($p, 'reserv') => 'bookings',
                    default => 'other',
                };
            };

            $mismatches = [];
            $directOwnerCategories = [
                'bookings' => ['count' => 0, 'amount' => 0],
                'recurring_invoices' => ['count' => 0, 'amount' => 0],
                'split_shares' => ['count' => 0, 'amount' => 0],
                'transfers' => ['count' => 0, 'amount' => 0],
                'pos_orders' => ['count' => 0, 'amount' => 0],
                'other' => ['count' => 0, 'amount' => 0],
            ];
            $receiversBreakdown = [];

            // Process Direct Owner Allocations
            foreach ($allocations as $allocation) {
                $sourceCat = $classifyPurpose($allocation->purpose);
                $directOwnerCategories[$sourceCat]['count']++;
                $directOwnerCategories[$sourceCat]['amount'] += (int) $allocation->amount;

                $rcvId = $allocation->receiving_connection_id;
                $rcvKey = $rcvId ? "connection:{$rcvId}" : 'direct_owner:unassigned';

                if (! isset($receiversBreakdown[$rcvKey])) {
                    $receiversBreakdown[$rcvKey] = [
                        'entity' => 'direct_owner',
                        'receiver' => $rcvKey,
                        'receiving_connection_id' => $rcvId,
                        'expected_total' => 0,
                        'verified_total' => 0,
                        'variance' => 0,
                        'abs_variance' => 0,
                    ];
                }
                $receiversBreakdown[$rcvKey]['expected_total'] += (int) $allocation->amount;

                $receipt = $allocation->receipt ?? ($allocation->payment_receipt_id ? OwnerPaymentReceipt::find($allocation->payment_receipt_id) : null);
                $attempt = $allocation->attempt ?? ($allocation->payment_attempt_id ? OwnerPaymentAttempt::find($allocation->payment_attempt_id) : null);
                $isPaid = $attempt ? in_array($attempt->status, ['paid', OwnerPaymentAttempt::STATUS_PAID], true) : true;

                $expectedAmount = (int) $allocation->amount;
                $receiptVerified = $receipt && in_array($receipt->status, [OwnerPaymentReceipt::STATUS_ALLOCATED, 'verified', 'paid'], true);
                $verifiedAmount = $receiptVerified ? (int) $receipt->amount : 0;

                if (! $receipt || ! $receiptVerified) {
                    // Allocation marked paid without matching verified provider receipt
                    $variance = $expectedAmount;
                    $mismatches[] = [
                        'id' => "alloc_missing_receipt_{$allocation->id}",
                        'entity' => 'direct_owner',
                        'receiver' => $rcvKey,
                        'receiving_connection_id' => $rcvId,
                        'type' => 'allocation_without_provider_event',
                        'reference' => "allocation:{$allocation->id}",
                        'source' => $sourceCat,
                        'purpose_id' => (string) $allocation->purpose_id,
                        'expected_amount' => $expectedAmount,
                        'verified_amount' => 0,
                        'variance' => $variance,
                        'abs_variance' => abs($variance),
                        'reason' => "Allocation #{$allocation->id} marked paid without verified provider receipt.",
                    ];
                } elseif ($expectedAmount !== $verifiedAmount) {
                    // Amount variance
                    $variance = $expectedAmount - $verifiedAmount;
                    $mismatches[] = [
                        'id' => "alloc_variance_{$allocation->id}",
                        'entity' => 'direct_owner',
                        'receiver' => $rcvKey,
                        'receiving_connection_id' => $rcvId,
                        'type' => 'amount_variance',
                        'reference' => "allocation:{$allocation->id}",
                        'source' => $sourceCat,
                        'purpose_id' => (string) $allocation->purpose_id,
                        'expected_amount' => $expectedAmount,
                        'verified_amount' => $verifiedAmount,
                        'variance' => $variance,
                        'abs_variance' => abs($variance),
                        'reason' => "Allocation #{$allocation->id} amount ({$expectedAmount}) differs from provider receipt ({$verifiedAmount}).",
                    ];
                } elseif (! $isPaid) {
                    // Allocation linked to unpaid attempt
                    $variance = $expectedAmount;
                    $mismatches[] = [
                        'id' => "alloc_unpaid_{$allocation->id}",
                        'entity' => 'direct_owner',
                        'receiver' => $rcvKey,
                        'receiving_connection_id' => $rcvId,
                        'type' => 'attempt_not_paid',
                        'reference' => "allocation:{$allocation->id}",
                        'source' => $sourceCat,
                        'purpose_id' => (string) $allocation->purpose_id,
                        'expected_amount' => $expectedAmount,
                        'verified_amount' => 0,
                        'variance' => $variance,
                        'abs_variance' => abs($variance),
                        'reason' => "Allocation #{$allocation->id} linked to non-paid attempt status '{$attempt?->status}'.",
                    ];
                }
            }

            // Process Direct Owner Receipts (check unallocated receipts)
            foreach ($ownerReceipts as $receipt) {
                $rcvId = $receipt->receiving_connection_id;
                $rcvKey = $rcvId ? "connection:{$rcvId}" : 'direct_owner:unassigned';

                if (! isset($receiversBreakdown[$rcvKey])) {
                    $receiversBreakdown[$rcvKey] = [
                        'entity' => 'direct_owner',
                        'receiver' => $rcvKey,
                        'receiving_connection_id' => $rcvId,
                        'expected_total' => 0,
                        'verified_total' => 0,
                        'variance' => 0,
                        'abs_variance' => 0,
                    ];
                }
                if (in_array($receipt->status, [OwnerPaymentReceipt::STATUS_ALLOCATED, 'verified', 'paid'], true)) {
                    $receiversBreakdown[$rcvKey]['verified_total'] += (int) $receipt->amount;
                }

                $hasAllocation = ($receipt->allocation !== null)
                    || $allocations->contains('payment_receipt_id', $receipt->id)
                    || ($receipt->status === OwnerPaymentReceipt::STATUS_ALLOCATED && $receipt->payment_attempt_id !== null);

                if (! $hasAllocation || in_array($receipt->status, [OwnerPaymentReceipt::STATUS_UNMATCHED, OwnerPaymentReceipt::STATUS_EXCEPTION, OwnerPaymentReceipt::STATUS_LATE], true)) {
                    $verifiedAmount = (int) $receipt->amount;
                    $variance = - $verifiedAmount; // Negative variance: receipt without allocation
                    $mismatches[] = [
                        'id' => "receipt_unallocated_{$receipt->id}",
                        'entity' => 'direct_owner',
                        'receiver' => $rcvKey,
                        'receiving_connection_id' => $rcvId,
                        'type' => 'provider_event_unallocated',
                        'reference' => "receipt:{$receipt->id}",
                        'source' => 'receipt',
                        'purpose_id' => (string) ($receipt->reference_code ?: $receipt->provider_event_id),
                        'expected_amount' => 0,
                        'verified_amount' => $verifiedAmount,
                        'variance' => $variance,
                        'abs_variance' => abs($variance),
                        'reason' => "Provider receipt #{$receipt->id} is unallocated (status: '{$receipt->status}').",
                    ];
                }
            }

            // Process Platform Subscriptions
            $platformExpected = (int) $platformAttempts->sum('amount');
            $platformVerified = (int) $platformReceipts->sum('amount');
            $receiversBreakdown['platform'] = [
                'entity' => 'platform',
                'receiver' => 'platform',
                'expected_total' => $platformExpected,
                'verified_total' => $platformVerified,
                'variance' => $platformExpected - $platformVerified,
                'abs_variance' => abs($platformExpected - $platformVerified),
            ];

            foreach ($platformAttempts as $pAttempt) {
                $pReceipt = $pAttempt->receipt ?? $platformReceipts->firstWhere('payment_attempt_id', $pAttempt->id);
                $expectedAmount = (int) $pAttempt->amount;
                $verifiedAmount = $pReceipt ? (int) $pReceipt->amount : 0;

                if (! $pReceipt) {
                    $variance = $expectedAmount;
                    $mismatches[] = [
                        'id' => "platform_attempt_no_receipt_{$pAttempt->id}",
                        'entity' => 'platform',
                        'receiver' => 'platform',
                        'type' => 'platform_attempt_without_receipt',
                        'reference' => "platform_attempt:{$pAttempt->id}",
                        'source' => 'platform_subscriptions',
                        'purpose_id' => (string) ($pAttempt->subscription_id ?? $pAttempt->id),
                        'expected_amount' => $expectedAmount,
                        'verified_amount' => 0,
                        'variance' => $variance,
                        'abs_variance' => abs($variance),
                        'reason' => "Platform subscription attempt #{$pAttempt->id} has no verified provider receipt.",
                    ];
                } elseif ($expectedAmount !== $verifiedAmount) {
                    $variance = $expectedAmount - $verifiedAmount;
                    $mismatches[] = [
                        'id' => "platform_variance_{$pAttempt->id}",
                        'entity' => 'platform',
                        'receiver' => 'platform',
                        'type' => 'platform_amount_variance',
                        'reference' => "platform_attempt:{$pAttempt->id}",
                        'source' => 'platform_subscriptions',
                        'purpose_id' => (string) ($pAttempt->subscription_id ?? $pAttempt->id),
                        'expected_amount' => $expectedAmount,
                        'verified_amount' => $verifiedAmount,
                        'variance' => $variance,
                        'abs_variance' => abs($variance),
                        'reason' => "Platform subscription attempt #{$pAttempt->id} amount ({$expectedAmount}) differs from receipt ({$verifiedAmount}).",
                    ];
                }
            }

            foreach ($platformReceipts as $pReceipt) {
                $hasSucceededAttempt = $pReceipt->paymentAttempt && $pReceipt->paymentAttempt->status === 'succeeded';
                if (! $hasSucceededAttempt) {
                    $verifiedAmount = (int) $pReceipt->amount;
                    $variance = - $verifiedAmount;
                    $mismatches[] = [
                        'id' => "platform_receipt_unallocated_{$pReceipt->id}",
                        'entity' => 'platform',
                        'receiver' => 'platform',
                        'type' => 'platform_receipt_unmatched',
                        'reference' => "platform_receipt:{$pReceipt->id}",
                        'source' => 'platform_subscriptions',
                        'purpose_id' => (string) $pReceipt->provider_transaction_id,
                        'expected_amount' => 0,
                        'verified_amount' => $verifiedAmount,
                        'variance' => $variance,
                        'abs_variance' => abs($variance),
                        'reason' => "Platform provider receipt #{$pReceipt->id} has no matching succeeded attempt.",
                    ];
                }
            }

            // Process Legacy Entity (ProviderEvents vs Journals not covered by direct owner/platform)
            $ownerReceiptEventIds = $ownerReceipts->pluck('provider_event_id')->filter()->all();
            $standaloneProviderEvents = $providerEvents->reject(fn ($pe) => in_array($pe->event_id, $ownerReceiptEventIds, true));
            $legacyProvSum = (int) $standaloneProviderEvents->sum('amount');
            $legacyJournSum = (int) $journals->whereIn('event_type', ['provider.collection', 'reservation.payment', 'booking.payment', 'wallet.deposit'])->sum('total_credit');
            $settlementTotal = (int) $settlements->sum('tenant_payable');
            $payoutTotal = (int) $payouts->sum('amount');

            $receiversBreakdown['legacy'] = [
                'entity' => 'legacy',
                'receiver' => 'legacy',
                'expected_total' => $legacyJournSum,
                'verified_total' => $legacyProvSum,
                'settlement_total' => $settlementTotal,
                'payout_total' => $payoutTotal,
                'variance' => $legacyJournSum - $legacyProvSum,
                'abs_variance' => abs($legacyJournSum - $legacyProvSum),
            ];

            if (($standaloneProviderEvents->isNotEmpty() || $journals->isNotEmpty()) && $legacyProvSum !== $legacyJournSum) {
                $legacyVariance = $legacyJournSum - $legacyProvSum;
                $mismatches[] = [
                    'id' => "legacy_variance_{$date}",
                    'entity' => 'legacy',
                    'receiver' => 'legacy',
                    'type' => 'legacy_provider_vs_journal',
                    'reference' => "legacy:{$date}",
                    'source' => 'legacy',
                    'purpose_id' => 'legacy_wallet_custody',
                    'expected_amount' => $legacyJournSum,
                    'verified_amount' => $legacyProvSum,
                    'variance' => $legacyVariance,
                    'abs_variance' => abs($legacyVariance),
                    'reason' => "Legacy provider total ({$legacyProvSum}) does not match legacy journal total ({$legacyJournSum}).",
                ];
            }

            // Update receiver breakdown variance calculations
            foreach ($receiversBreakdown as $k => $rcvData) {
                $var = $rcvData['expected_total'] - $rcvData['verified_total'];
                $receiversBreakdown[$k]['variance'] = $var;
                $receiversBreakdown[$k]['abs_variance'] = abs($var);
            }

            // CRITICAL (D46): Do NOT net variances! Sum of absolute variances ensures no mismatch is cancelled out.
            $totalMismatch = (int) array_sum(array_column($mismatches, 'abs_variance'));

            $verifiedOwnerReceiptsTotal = (int) $ownerReceipts->whereIn('status', [OwnerPaymentReceipt::STATUS_ALLOCATED, 'verified', 'paid'])->sum('amount');
            $providerTotal = $verifiedOwnerReceiptsTotal + $legacyProvSum + $platformVerified;
            $journalTotal = $legacyJournSum + (int) $allocations->sum('amount') + $platformExpected;

            $details = [
                'breakdown' => [
                    'direct_owner' => [
                        'bookings' => $directOwnerCategories['bookings'],
                        'recurring_invoices' => $directOwnerCategories['recurring_invoices'],
                        'split_shares' => $directOwnerCategories['split_shares'],
                        'transfers' => $directOwnerCategories['transfers'],
                        'pos_orders' => $directOwnerCategories['pos_orders'],
                        'refunds' => [
                            'count' => $refunds->count(),
                            'amount' => (int) $refunds->sum('amount'),
                        ],
                        'total_expected' => (int) $allocations->sum('amount'),
                        'total_verified' => $verifiedOwnerReceiptsTotal,
                    ],
                    'platform' => [
                        'subscriptions' => [
                            'count' => $platformAttempts->count(),
                            'amount' => $platformExpected,
                        ],
                        'total_expected' => $platformExpected,
                        'total_verified' => $platformVerified,
                    ],
                    'legacy' => [
                        'journals' => [
                            'count' => $journals->count(),
                            'amount' => $legacyJournSum,
                        ],
                        'provider_events' => [
                            'count' => $standaloneProviderEvents->count(),
                            'amount' => $legacyProvSum,
                        ],
                        'settlements' => [
                            'count' => $settlements->count(),
                            'amount' => $settlementTotal,
                        ],
                        'payouts' => [
                            'count' => $payouts->count(),
                            'amount' => $payoutTotal,
                        ],
                        'total_expected' => $legacyJournSum,
                        'total_verified' => $legacyProvSum,
                    ],
                ],
                'sources' => [
                    'bookings' => $directOwnerCategories['bookings'],
                    'recurring_invoices' => $directOwnerCategories['recurring_invoices'],
                    'split_shares' => $directOwnerCategories['split_shares'],
                    'transfers' => $directOwnerCategories['transfers'],
                    'pos_orders' => $directOwnerCategories['pos_orders'],
                    'refunds' => [
                        'count' => $refunds->count(),
                        'amount' => (int) $refunds->sum('amount'),
                    ],
                    'platform_subscriptions' => [
                        'count' => $platformAttempts->count(),
                        'amount' => $platformExpected,
                    ],
                    'legacy' => [
                        'count' => $journals->count(),
                        'amount' => $legacyJournSum,
                    ],
                ],
                'receivers' => $receiversBreakdown,
                'mismatches' => $mismatches,
                'total_mismatch' => $totalMismatch,
                'provider_vs_journal' => $providerTotal - $journalTotal,
                'journal_total' => $journalTotal,
                'settlement_total' => $settlementTotal,
                'payout_total' => $payoutTotal,
                'checked_at' => now()->toIso8601String(),
            ];

            $run->update([
                'status' => ($totalMismatch === 0 && count($mismatches) === 0) ? 'matched' : 'mismatch',
                'provider_total' => $providerTotal,
                'journal_total' => $journalTotal,
                'settlement_total' => $settlementTotal,
                'payout_total' => $payoutTotal,
                'mismatch_amount' => $totalMismatch,
                'details' => $details,
                'completed_at' => now(),
            ]);

            if ($totalMismatch !== 0 || count($mismatches) > 0) {
                app(ObservabilityService::class)->alert('payout_mismatch', [
                    'amount' => $totalMismatch,
                    'organization_id' => $organization->id,
                    'run_id' => $run->id,
                ]);

                $existingException = FinanceException::query()
                    ->where('organization_id', $organization->id)
                    ->where('type', 'reconciliation_mismatch')
                    ->where('reference', "reconciliation:{$date}")
                    ->where('status', 'open')
                    ->first();

                if ($existingException) {
                    $existingException->update([
                        'amount' => $totalMismatch,
                        'links' => array_merge($existingException->links ?? [], ['reconciliation_run_id' => $run->id]),
                        'metadata' => $details,
                    ]);
                } else {
                    FinanceException::create([
                        'organization_id' => $organization->id,
                        'type' => 'reconciliation_mismatch',
                        'reference' => "reconciliation:{$date}",
                        'status' => 'open',
                        'severity' => 'high',
                        'amount' => $totalMismatch,
                        'description' => 'Provider, journal, settlement, or allocation totals do not reconcile across sources.',
                        'links' => ['reconciliation_run_id' => $run->id],
                        'metadata' => $details,
                        'detected_at' => now(),
                        'due_at' => now()->addMinutes(15),
                    ]);
                }
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

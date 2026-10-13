<?php

namespace App\Services\Booking;

use App\Models\FinanceException;
use App\Models\MonthlyInvoice;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerRefundAllocation;
use App\Models\OwnerRefundObligation;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\OwnerRefundService;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Returns recurring occurrences to inventory. Shared by the overdue sweep (one unpaid period), contract cancellation
 * (verified paid value becomes an owner refund obligation) and the repair of cancels made by the pre-fix admin path.
 */
class RecurringReleaseService
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly OwnerPaymentAttemptService $attempts,
        private readonly OwnerRefundService $refunds,
    ) {}

    /**
     * @param  iterable<RecurringOccurrence>  $occurrences
     */
    public function releaseOccurrences(iterable $occurrences): int
    {
        $released = 0;
        foreach ($occurrences as $occurrence) {
            if ($occurrence->reservation) {
                $this->reservations->release($occurrence->reservation, 'cancelled');
            }
            // A repair keeps the time the old admin cancel marked the occurrence released.
            $occurrence->update(['status' => 'released', 'released_at' => $occurrence->released_at ?? now()]);
            $released++;
        }

        return $released;
    }

    /**
     * @param  array<int, int>  $invoiceIds
     */
    public function expirePendingAttempts(int $seriesId, array $invoiceIds): void
    {
        foreach ($invoiceIds as $invoiceId) {
            $this->attempts->expirePendingForPurpose('recurring_period', "rec_series:{$seriesId}:invoice:{$invoiceId}");
        }
        $this->attempts->expirePendingForPurpose('recurring_period', "rec_series:{$seriesId}:full");
    }

    /**
     * Cancel a whole series: release every future occurrence, void open invoices whose period has not
     * started and turn verified paid value into owner refund obligations. The caller owns the
     * transaction and runs this in the series' organization context.
     */
    public function cancelSeries(int $seriesId, User $actor, string $reason): void
    {
        $series = $this->lockSeries($seriesId);
        if ($series->status === 'cancelled') {
            return; // A repeated cancel must not release or refund twice.
        }

        $this->close($series, $series->occurrences()->whereIn('status', ['held', 'confirmed']), $actor, $reason, [
            'cancelled_at' => now()->toIso8601String(),
            'cancel_reason' => $reason,
            'cancelled_by' => $actor->id,
        ]);
    }

    /**
     * Finish a cancel made by the pre-fix admin path, which marked future occurrences released but kept their
     * reservations, open invoices and attempts. Same caller contract as cancelSeries; null once nothing is left.
     *
     * @return array{past_live: int, released: int, voided: int, refunds: int, refund_amount: int}|null
     */
    public function repairLegacyCancel(int $seriesId, User $actor): ?array
    {
        $metadata = RecurringSeries::query()->whereKey($seriesId)->value('metadata');
        if (! data_get($metadata, 'cancelled_at')) {
            return null; // Only contracts an admin cancelled: a live contract's payment attempts must stay open.
        }

        $series = $this->lockSeries($seriesId);
        $stranded = $series->occurrences()->where('status', 'released')
            ->whereHas('reservation', fn ($q) => $q->whereIn('status', ['held', 'confirmed']));
        if (! (clone $stranded)->where('starts_at', '>', now())->exists()) {
            return null;
        }

        // Sessions that started since the old cancel kept a valid booking and may have been played: report, never refund.
        $pastLive = (clone $stranded)->where('starts_at', '<=', now())->count();
        $reason = (string) (data_get($series->metadata, 'cancel_reason') ?: 'Admin huỷ theo yêu cầu');

        return ['past_live' => $pastLive] + $this->close($series, $stranded, $actor, $reason, [
            'repaired_at' => now()->toIso8601String(),
            'repaired_by' => $actor->id,
        ]);
    }

    private function lockSeries(int $seriesId): RecurringSeries
    {
        // Lock attempts before the series, as the payment path does, so a racing receipt cannot deadlock.
        $this->expirePendingAttempts($seriesId, MonthlyInvoice::query()->where('recurring_series_id', $seriesId)->pluck('id')->all());

        return RecurringSeries::query()->whereKey($seriesId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Release the given occurrences that have not started, void open invoices whose period has not started,
     * turn verified paid value into owner refund obligations and mark the series cancelled.
     *
     * @param  HasMany<RecurringOccurrence, RecurringSeries>  $occurrences
     * @return array{released: int, voided: int, refunds: int, refund_amount: int}
     */
    private function close(RecurringSeries $series, HasMany $occurrences, User $actor, string $reason, array $metadata): array
    {
        $invoices = MonthlyInvoice::query()->where('recurring_series_id', $series->id)->lockForUpdate()->get();
        $released = $occurrences->where('starts_at', '>', now())->with('reservation')->lockForUpdate()->get();
        $this->releaseOccurrences($released);

        $voided = $invoices->filter(fn (MonthlyInvoice $invoice) => in_array($invoice->status, ['pending', 'guaranteed'], true)
            && ! $series->occurrences()->whereBetween('starts_at', $this->period($invoice))->where('starts_at', '<=', now())->exists());
        $voided->each->update(['status' => 'cancelled']);

        $refundIds = $this->recordPaidValue($series, $invoices, $released, $actor, $reason);
        $series->update([
            'status' => 'cancelled',
            'metadata' => array_merge($series->metadata ?? [], $metadata, [
                'refund_obligation_ids' => array_merge(data_get($series->metadata, 'refund_obligation_ids', []), $refundIds),
            ]),
        ]);

        $refundAmount = (int) OwnerRefundObligation::query()->whereIn('id', $refundIds)->sum('amount');

        return ['released' => $released->count(), 'voided' => $voided->count(), 'refunds' => count($refundIds), 'refund_amount' => $refundAmount];
    }

    /**
     * Released sessions of a paid period: a verified owner receipt becomes one owner refund obligation
     * per payment attempt; a period marked paid without one (pre-D43 manual reference) creates no
     * financial effect and opens a finance exception for explicit resolution.
     *
     * @return array<int, int>
     */
    private function recordPaidValue(RecurringSeries $series, Collection $invoices, Collection $released, User $actor, string $reason): array
    {
        $attempts = OwnerPaymentAttempt::query()
            ->where('organization_id', $series->organization_id)
            ->where('purpose', 'recurring_period')
            ->where('purpose_id', 'like', "rec_series:{$series->id}:%")
            ->where('status', OwnerPaymentAttempt::STATUS_PAID)
            ->get()
            ->keyBy('reference');

        $byReference = [];
        foreach ($invoices->where('status', 'paid') as $invoice) {
            $paid = $released->filter(fn (RecurringOccurrence $o) => $o->amount > 0 && $o->starts_at->between(...$this->period($invoice)));
            if ($paid->isEmpty()) {
                continue;
            }
            if (! $attempts->has($invoice->payment_reference)) {
                $this->openUnverifiedPaidException($series, $invoice, (int) $paid->sum('amount'));

                continue;
            }
            $byReference[$invoice->payment_reference] = ($byReference[$invoice->payment_reference] ?? collect())->merge($paid);
        }

        $refundIds = [];
        foreach ($byReference as $reference => $paid) {
            $refund = $this->refundAttempt($series, $attempts[$reference], $paid, $actor, $reason);
            if ($refund) {
                $refundIds[] = (int) $refund->id;
            }
        }

        return $refundIds;
    }

    /**
     * The venue ended the contract and no customer fee was disclosed for it, so the refund is fee-free and
     * approved; the owner still executes the transfer. Bounded by the verified allocation minus earlier refunds.
     */
    private function refundAttempt(RecurringSeries $series, OwnerPaymentAttempt $attempt, Collection $occurrences, User $actor, string $reason): ?OwnerRefundObligation
    {
        $allocations = OwnerPaymentAllocation::query()->where('payment_attempt_id', $attempt->id)->orderBy('id')->get();
        $refunded = OwnerRefundAllocation::query()
            ->whereIn('owner_payment_allocation_id', $allocations->modelKeys())
            ->whereHas('obligation', fn ($q) => $q->where('status', '!=', OwnerRefundObligation::STATUS_REJECTED))
            ->get()
            ->groupBy('owner_payment_allocation_id');
        $remaining = $allocations->mapWithKeys(fn (OwnerPaymentAllocation $a) => [
            $a->id => max(0, (int) $a->amount - (int) ($refunded->get($a->id)?->sum('gross_amount') ?? 0)),
        ]);
        $gross = min((int) $occurrences->sum('amount'), (int) $remaining->sum());
        if ($gross <= 0) {
            return null;
        }

        $reservation = $occurrences->first()->reservation;
        $policy = (array) data_get($reservation?->metadata, 'booking_policy_snapshot', []);
        $dueHours = (int) data_get($policy, 'settings.cancellation.refund_due_hours', 48);
        $codes = $occurrences->map(fn (RecurringOccurrence $o) => $o->reservation?->code)->filter()->values()->all();
        $payer = $series->user;
        $refund = OwnerRefundObligation::create([
            'organization_id' => $series->organization_id,
            'venue_id' => $attempt->venue_id,
            'reservation_id' => $reservation?->id,
            'payer_id' => $series->user_id,
            'payment_attempt_id' => $attempt->id,
            'reference_code' => 'RF'.Str::upper(Str::random(18)),
            'idempotency_key' => "recurring_cancel:{$series->id}:{$attempt->id}",
            'request_fingerprint' => hash('sha256', "recurring_cancel:{$series->id}:{$attempt->id}:{$gross}"),
            'slot_ids' => [],
            'gross_amount' => $gross,
            'fee_amount' => 0,
            'amount' => $gross,
            'currency' => $attempt->currency,
            'status' => OwnerRefundObligation::STATUS_APPROVED,
            'reason' => $reason,
            'policy_snapshot' => array_merge($policy, [
                'recurring_cancellation' => ['series_id' => $series->id, 'reservation_codes' => $codes, 'fee_basis' => 'venue_cancelled_no_fee'],
            ]),
            'recipient_ciphertext' => Crypt::encryptString(json_encode(
                ['name' => $payer?->name, 'user_id' => $series->user_id, 'phone' => $payer?->phone],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )),
            'due_at' => now()->addHours($dueHours > 0 ? $dueHours : 48),
            'requested_at' => now(),
            'approved_at' => now(),
            'requested_by' => $actor->id,
            'approved_by' => $actor->id,
        ]);

        $left = $gross;
        foreach ($allocations as $allocation) {
            $share = min($left, $remaining[$allocation->id]);
            if ($share <= 0) {
                continue;
            }
            OwnerRefundAllocation::create([
                'owner_refund_obligation_id' => $refund->id,
                'owner_payment_allocation_id' => $allocation->id,
                'gross_amount' => $share,
                'fee_amount' => 0,
                'amount' => $share,
                'created_at' => now(),
            ]);
            $left -= $share;
        }
        $this->refunds->recordEvidence($refund, 'approved', $actor->id, [
            'trigger' => 'recurring_series_cancelled',
            'series_id' => $series->id,
            'reason' => $reason,
            'reservation_codes' => $codes,
            'refund_amount' => $gross,
        ]);

        return $refund;
    }

    private function openUnverifiedPaidException(RecurringSeries $series, MonthlyInvoice $invoice, int $amount): void
    {
        FinanceException::create([
            'organization_id' => $series->organization_id,
            'type' => 'recurring_unverified_paid_release',
            'severity' => 'high',
            'status' => 'open',
            'amount' => $amount,
            'currency' => 'VND',
            'reference' => 'monthly-invoice:'.$invoice->id,
            'description' => 'Cancelled recurring sessions were in a period marked paid without a verified owner receipt; resolve any refund explicitly.',
            'links' => ['recurring_series_id' => $series->id, 'monthly_invoice_id' => $invoice->id],
            'detected_at' => now(),
            'due_at' => now()->addDay(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(MonthlyInvoice $invoice): array
    {
        return [Carbon::parse($invoice->period_start)->startOfDay(), Carbon::parse($invoice->period_end)->endOfDay()];
    }
}

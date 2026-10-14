<?php

namespace App\Services\Booking;

use App\Models\MonthlyInvoice;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;

/**
 * Returns recurring occurrences to inventory. Shared by the overdue sweep (one unpaid period) and contract
 * cancellation (unpaid future occurrences only; paid ones stay booked).
 */
class RecurringReleaseService
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly OwnerPaymentAttemptService $attempts,
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
            $occurrence->update(['status' => 'released', 'released_at' => now()]);
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
     * End a contract: release future occurrences outside paid periods, void open invoices whose period has not
     * started and mark the series cancelled. Paid occurrences stay confirmed; nothing is refunded. The caller
     * owns the transaction and runs this in the series' organization context.
     */
    public function cancelSeries(int $seriesId, User $actor, string $reason): void
    {
        // Lock attempts before the series, as the payment path does, so a racing receipt cannot deadlock.
        $this->expirePendingAttempts($seriesId, MonthlyInvoice::query()->where('recurring_series_id', $seriesId)->pluck('id')->all());
        $series = RecurringSeries::query()->whereKey($seriesId)->lockForUpdate()->firstOrFail();
        if ($series->status === 'cancelled') {
            return; // A repeated cancel must not release twice.
        }

        $invoices = MonthlyInvoice::query()->where('recurring_series_id', $series->id)->lockForUpdate()->get();
        $paidPeriods = $invoices->where('status', 'paid')->map(fn (MonthlyInvoice $invoice) => $this->period($invoice));
        $unpaid = $series->occurrences()->whereIn('status', ['held', 'confirmed'])->where('starts_at', '>', now())
            ->with('reservation')->lockForUpdate()->get()
            ->reject(fn (RecurringOccurrence $o) => $paidPeriods->contains(fn (array $period) => $o->starts_at->between(...$period)));
        $this->releaseOccurrences($unpaid);

        $invoices->filter(fn (MonthlyInvoice $invoice) => in_array($invoice->status, ['pending', 'guaranteed'], true)
            && ! $series->occurrences()->whereBetween('starts_at', $this->period($invoice))->where('starts_at', '<=', now())->exists())
            ->each->update(['status' => 'cancelled']);

        $series->update([
            'status' => 'cancelled',
            'metadata' => array_merge($series->metadata ?? [], [
                'cancelled_at' => now()->toIso8601String(),
                'cancel_reason' => $reason,
                'cancelled_by' => $actor->id,
            ]),
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

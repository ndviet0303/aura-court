<?php

namespace App\Services\Booking;

use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\MonthlyInvoice;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\VenueBookingPolicy;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\OutboxService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringSeriesService
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly ReservationService $reservations,
        private readonly OutboxService $outbox,
        private readonly RecurringReleaseService $releases,
    ) {}

    public function createAndConfirm(User $user, int $organizationId, array $data): RecurringSeries|array
    {
        if ($user->isRestricted()) {
            abort(403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        }
        if (! $user->hasVerifiedEmail()) {
            abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/v1/auth/email/verification-notification.');
        }

        $court = Court::with(['venue', 'sportZone'])->findOrFail((int) $data['court_id']);
        if ($court->venue?->organization_id) {
            $organizationId = (int) $court->venue->organization_id;
        }

        $frequency = strtolower((string) ($data['frequency'] ?? ''));
        if (! in_array($frequency, ['weekly', 'biweekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['frequency' => 'Frequency must be weekly, biweekly or monthly.']);
        }
        $firstStart = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone'))->setSecond(0);
        $firstEnd = isset($data['ends_at']) ? Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone'))->setSecond(0) : $firstStart->copy()->addHour();
        $sessionMinutes = (int) abs($firstStart->diffInMinutes($firstEnd));
        // Fail fast (before any transaction) if the session is not a whole multiple of the zone slot.
        $this->sessionSlotStarts($court, $firstStart, $sessionMinutes);
        $until = isset($data['until']) ? Carbon::parse($data['until'])->setTimezone(config('app.timezone'))->setSecond(0) : null;
        if ($until && $until->greaterThan($firstStart->copy()->addMonths(12))) {
            throw ValidationException::withMessages(['until' => 'A recurring series may not exceed a 12-month horizon.']);
        }
        $requested = (int) ($data['occurrences'] ?? 0);
        if ($requested > 104) {
            throw ValidationException::withMessages(['occurrences' => 'A recurring series may not exceed 104 occurrences.']);
        }
        $occurrences = $this->generateOccurrences(
            $court,
            $frequency,
            $this->normalizeWeekdays($data['weekdays'] ?? []),
            $firstStart,
            $firstEnd,
            $until,
            $requested > 0 ? $requested : null,
        );
        if ($occurrences === []) {
            throw ValidationException::withMessages(['starts_at' => 'No occurrences fall within the requested recurrence window.']);
        }

        // Flag ON: accept the client's preview, hold pending, and confirm only on verified payment.
        // Flag OFF: keep the legacy immediate-confirm path below unchanged.
        if (app(FeatureFlagService::class)->enabled('direct_owner_payments', $organizationId)) {
            return $this->acceptAndHold($user, $organizationId, $court, $data, $frequency, $firstStart, $firstEnd, $sessionMinutes, $occurrences);
        }

        return DB::transaction(function () use ($user, $organizationId, $court, $data, $frequency, $firstStart, $firstEnd, $sessionMinutes, $occurrences): RecurringSeries {
            $series = RecurringSeries::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'court_id' => (int) $data['court_id'],
                'frequency' => $frequency,
                'starts_at' => $firstStart,
                'ends_at' => $firstEnd,
                'series_ends_at' => $occurrences[array_key_last($occurrences)]['ends_at'],
                'occurrence_count' => count($occurrences),
                'status' => 'draft',
                'metadata' => ['horizon' => end($occurrences)['starts_at']->toIso8601String()],
            ]);
            $total = 0;
            foreach ($occurrences as $sequence => $occurrence) {
                $start = $occurrence['starts_at'];
                $quote = $this->quotes->create($user->id, $organizationId, $series->court_id, $this->sessionSlotStarts($court, $start, $sessionMinutes));
                $reservation = $this->reservations->hold($user, $organizationId, [
                    'quote_id' => $quote->id,
                    'metadata' => ['type' => 'recurring_series', 'series_id' => $series->id],
                ]);
                $reservation = $this->reservations->confirm($reservation);
                $amount = (int) $reservation->total_amount;
                $total += $amount;
                RecurringOccurrence::create([
                    'organization_id' => $organizationId,
                    'recurring_series_id' => $series->id,
                    'reservation_id' => $reservation->id,
                    'sequence' => $sequence + 1,
                    'starts_at' => $start,
                    'ends_at' => $occurrence['ends_at'],
                    'amount' => $amount,
                    'status' => 'confirmed',
                ]);
            }
            $series->update(['status' => 'confirmed', 'total_amount' => $total, 'next_invoice_due_at' => $firstStart->copy()->subDays(7)]);
            $this->createInvoices($series->fresh('occurrences'));

            return $series->fresh(['occurrences.reservation', 'invoices']);
        });
    }

    /**
     * Flag-ON accept/hold path. Re-prices and re-checks the client's accepted preview against
     * live inventory, rejects drift (409), applies the venue recurring conflict policy, then
     * HOLDS every accepted occurrence at its period-payment deadline. Occurrences confirm only
     * when a verified receiving event pays their period (or immediately when the venue guarantees).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{starts_at: Carbon, ends_at: Carbon, weekday: int}>  $occurrences
     * @return RecurringSeries|array<string, mixed>
     */
    private function acceptAndHold(User $user, int $organizationId, Court $court, array $data, string $frequency, Carbon $firstStart, Carbon $firstEnd, int $sessionMinutes, array $occurrences): RecurringSeries|array
    {
        $settings = $this->recurringPolicySettings($court);
        $conflictPolicy = (string) data_get($settings, 'recurring.conflict_policy', 'all_or_nothing');
        $paymentPolicy = (string) data_get($settings, 'recurring.payment_policy', 'per_period');
        $guaranteeConfirm = (bool) data_get($settings, 'recurring.guarantee_confirm', false);
        $graceHours = (int) data_get($settings, 'recurring.overdue_release_hours', 24);
        $holdTtlMinutes = (int) data_get($settings, 'booking.hold_ttl_minutes', 30);
        $slotMinutes = (int) ($court->sportZone?->slot_duration ?: 60);

        // Re-price + re-check conflicts against LIVE inventory using the shared generator output.
        $priced = [];
        foreach ($occurrences as $index => $occurrence) {
            $start = $occurrence['starts_at'];
            $end = $occurrence['ends_at'];
            $snapshot = $this->quotes->priceSnapshots($court, $this->sessionSlotStarts($court, $start, $sessionMinutes), $slotMinutes, true);
            $reason = $this->overlapReason($court, $start, $end);
            $priced[] = [
                'index' => $index + 1,
                'starts_at' => $start,
                'ends_at' => $end,
                'starts_at_utc' => $start->copy()->utc()->toIso8601String(),
                'ends_at_utc' => $end->copy()->utc()->toIso8601String(),
                'weekday' => $occurrence['weekday'],
                'price' => (int) collect($snapshot)->sum('unit_price'),
                'conflict' => $reason !== null,
                'conflict_reason' => $reason,
            ];
        }

        // D7: the client MUST echo the accepted preview; reject drift before touching inventory.
        $this->assertAcceptedSnapshotMatches($data['accepted'] ?? null, $priced);

        $conflicted = array_values(array_filter($priced, static fn (array $o): bool => $o['conflict']));
        $acceptPartial = (bool) ($data['accept_partial'] ?? false);
        if ($conflicted !== []) {
            if ($conflictPolicy === 'all_or_nothing') {
                abort(409, 'Một hoặc nhiều buổi trong chuỗi đã bị đặt; toàn bộ yêu cầu bị từ chối theo chính sách all-or-nothing.');
            }
            if (! $acceptPartial) {
                $keep = array_values(array_filter($priced, static fn (array $o): bool => ! $o['conflict']));

                return [
                    'status' => 'partial_conflict',
                    'currency' => 'VND',
                    'held_count' => count($keep),
                    'revised_total' => (int) array_sum(array_column($keep, 'price')),
                    'excluded' => array_map(static fn (array $o): array => [
                        'index' => $o['index'],
                        'starts_at' => $o['starts_at_utc'],
                        'ends_at' => $o['ends_at_utc'],
                        'conflict_reason' => $o['conflict_reason'],
                    ], $conflicted),
                ];
            }
        }

        $toHold = ($conflicted !== [] && $acceptPartial)
            ? array_values(array_filter($priced, static fn (array $o): bool => ! $o['conflict']))
            : $priced;
        if ($toHold === []) {
            abort(409, 'Không còn buổi nào khả dụng để giữ chỗ.');
        }

        return DB::transaction(function () use ($user, $organizationId, $court, $frequency, $firstStart, $firstEnd, $sessionMinutes, $toHold, $conflictPolicy, $paymentPolicy, $guaranteeConfirm, $graceHours, $holdTtlMinutes, $acceptPartial): RecurringSeries {
            $lastEnd = $toHold[array_key_last($toHold)]['ends_at'];
            $series = RecurringSeries::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'court_id' => $court->id,
                'frequency' => $frequency,
                'starts_at' => $firstStart,
                'ends_at' => $firstEnd,
                'series_ends_at' => $lastEnd,
                'occurrence_count' => count($toHold),
                'status' => 'draft',
                'metadata' => [
                    'horizon' => $lastEnd->copy()->toIso8601String(),
                    'accepted_snapshot' => [
                        'conflict_policy' => $conflictPolicy,
                        'payment_policy' => $paymentPolicy,
                        'guarantee_confirm' => $guaranteeConfirm,
                        'overdue_release_hours' => $graceHours,
                        'accept_partial' => $acceptPartial,
                        'accepted_at' => now()->toIso8601String(),
                        'occurrences' => array_map(static fn (array $o): array => [
                            'starts_at' => $o['starts_at_utc'],
                            'ends_at' => $o['ends_at_utc'],
                            'price' => $o['price'],
                        ], $toHold),
                    ],
                ],
            ]);

            $total = 0;
            foreach ($toHold as $sequence => $occurrence) {
                $start = $occurrence['starts_at'];
                $deadline = $this->periodDeadline($start, $graceHours, $holdTtlMinutes);
                $quote = $this->quotes->create($user->id, $organizationId, $series->court_id, $this->sessionSlotStarts($court, $start, $sessionMinutes));
                $reservation = $this->reservations->hold($user, $organizationId, [
                    'quote_id' => $quote->id,
                    'metadata' => ['type' => 'recurring_series', 'series_id' => $series->id],
                    'defer_payment_initiation' => true,
                    'hold_expires_at_override' => $deadline,
                ]);
                $amount = (int) $reservation->total_amount;
                $total += $amount;
                // Zero-payable holds already confirm inside hold(); guarantee confirms up front.
                if ($guaranteeConfirm && $reservation->status !== 'confirmed') {
                    $this->confirmReservationInline($reservation, now());
                }
                RecurringOccurrence::create([
                    'organization_id' => $organizationId,
                    'recurring_series_id' => $series->id,
                    'reservation_id' => $reservation->id,
                    'sequence' => $sequence + 1,
                    'starts_at' => $start,
                    'ends_at' => $occurrence['ends_at'],
                    'amount' => $amount,
                    'status' => ($guaranteeConfirm || $reservation->status === 'confirmed') ? 'confirmed' : 'held',
                ]);
            }

            $this->createInvoices($series->fresh('occurrences'));
            // Fully discounted periods owe no bank transfer, but must not masquerade as paid.
            MonthlyInvoice::query()->where('recurring_series_id', $series->id)
                ->where('amount', 0)->update(['status' => 'zero_payable']);
            $series->refresh();
            if ($guaranteeConfirm) {
                // A policy guarantee permits play, but it is not proof that the owner received money.
                MonthlyInvoice::query()->where('recurring_series_id', $series->id)
                    ->where('status', 'pending')->update(['status' => 'guaranteed']);
            } else {
                $this->initiateSeriesPayments($series->load(['occurrences.reservation', 'invoices']), $user, $paymentPolicy, $graceHours, $holdTtlMinutes);
            }

            $anyHeld = $series->occurrences()->where('status', 'held')->exists();
            $series->update([
                'status' => $anyHeld ? 'pending' : 'confirmed',
                'total_amount' => $total,
                'next_invoice_due_at' => $firstStart->copy()->subDays(7),
            ]);

            return $series->fresh(['occurrences.reservation', 'invoices']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function recurringPolicySettings(Court $court): array
    {
        $policy = $court->venue?->currentBookingPolicy()->first();
        abort_unless($policy instanceof VenueBookingPolicy, 409, 'A captured venue booking policy is required for recurring direct payment.');

        return (array) $policy->settings;
    }

    /**
     * Reject a stale/altered accepted preview before any inventory is touched (D7 drift lock).
     *
     * @param  mixed  $accepted
     * @param  array<int, array<string, mixed>>  $priced
     */
    private function assertAcceptedSnapshotMatches($accepted, array $priced): void
    {
        if (! is_array($accepted) || ! isset($accepted['occurrences']) || ! is_array($accepted['occurrences'])) {
            throw ValidationException::withMessages(['accepted' => 'The accepted preview occurrence list is required.']);
        }

        $serverByStart = [];
        foreach ($priced as $o) {
            $serverByStart[$o['starts_at_utc']] = $o['price'];
        }
        $clientByStart = [];
        foreach ($accepted['occurrences'] as $row) {
            $clientByStart[Carbon::parse($row['starts_at'])->utc()->toIso8601String()] = (int) $row['price'];
        }

        if (count($serverByStart) !== count($clientByStart)
            || array_diff_key($serverByStart, $clientByStart) !== []
            || array_diff_key($clientByStart, $serverByStart) !== []) {
            abort(409, 'Danh sách buổi đã thay đổi so với bản xem trước; vui lòng xem trước lại.');
        }
        foreach ($serverByStart as $start => $price) {
            if ($clientByStart[$start] !== $price) {
                abort(409, 'Giá đã thay đổi so với bản xem trước; vui lòng xem trước lại.');
            }
        }
        if (isset($accepted['total']) && (int) $accepted['total'] !== (int) array_sum($serverByStart)) {
            abort(409, 'Tổng tiền đã thay đổi so với bản xem trước; vui lòng xem trước lại.');
        }
    }

    /**
     * The payment deadline for the period an occurrence belongs to: the invoice due date plus the
     * venue grace window, floored to a near-term deadline so the hold/attempt stays valid when the
     * period is already under way.
     */
    private function periodDeadline(Carbon $occurrenceStart, int $graceHours, int $holdTtlMinutes): CarbonInterface
    {
        $deadline = $occurrenceStart->copy()->startOfMonth()->subDays(7)->addHours(max(0, $graceHours));
        $floor = now()->addMinutes(max(1, $holdTtlMinutes));

        return $deadline->greaterThan($floor) ? $deadline : $floor;
    }

    private function confirmReservationInline(Reservation $reservation, CarbonInterface $at): void
    {
        $reservation->forceFill(['status' => 'confirmed', 'confirmed_at' => $at])->save();
        $reservation->slots()->where('status', 'held')->update(['status' => 'confirmed']);
        $reservation->entitlements()->where('status', 'held')->update(['status' => 'active']);
    }

    private function initiateSeriesPayments(RecurringSeries $series, User $payer, string $paymentPolicy, int $graceHours, int $holdTtlMinutes): void
    {
        $payment = app(ReservationPaymentService::class);
        $held = $series->occurrences->where('status', 'held')->values();
        if ($held->isEmpty()) {
            return;
        }

        if ($paymentPolicy === 'full_series') {
            $amount = (int) $held->sum('amount');
            if ($amount <= 0) {
                return;
            }
            $payment->initiate($held->first()->reservation, $payer, 'recurring_period', 'rec_series:'.$series->id.':full', $amount, $this->earliestDeadline($held, $graceHours, $holdTtlMinutes));

            return;
        }

        foreach ($series->invoices as $invoice) {
            $periodStart = Carbon::parse($invoice->period_start)->startOfDay();
            $periodEnd = Carbon::parse($invoice->period_end)->endOfDay();
            $periodHeld = $held->filter(static fn (RecurringOccurrence $o): bool => $o->starts_at->between($periodStart, $periodEnd))->values();
            $amount = (int) $periodHeld->sum('amount');
            if ($periodHeld->isEmpty() || $amount <= 0) {
                continue;
            }
            $payment->initiate($periodHeld->first()->reservation, $payer, 'recurring_period', 'rec_series:'.$series->id.':invoice:'.$invoice->id, $amount, $this->periodDeadline($periodHeld->first()->starts_at, $graceHours, $holdTtlMinutes));
        }
    }

    private function earliestDeadline(Collection $occurrences, int $graceHours, int $holdTtlMinutes): CarbonInterface
    {
        $deadline = null;
        foreach ($occurrences as $occurrence) {
            $candidate = $this->periodDeadline($occurrence->starts_at, $graceHours, $holdTtlMinutes);
            if ($deadline === null || $candidate->lessThan($deadline)) {
                $deadline = $candidate;
            }
        }

        return $deadline;
    }

    /**
     * @return array{series_id: int, invoice_id: int|null}|null
     */
    private function parseAttemptTarget(string $purposeId): ?array
    {
        if (! preg_match('/^rec_series:([1-9]\d*):(full|invoice:([1-9]\d*))$/D', $purposeId, $matches)) {
            return null;
        }

        return ['series_id' => (int) $matches[1], 'invoice_id' => isset($matches[3]) ? (int) $matches[3] : null];
    }

    private function confirmableOccurrencesQuery(OwnerPaymentAttempt $attempt): Builder
    {
        $target = $this->parseAttemptTarget((string) $attempt->purpose_id);
        $query = RecurringOccurrence::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('recurring_series_id', $target['series_id'] ?? 0)
            ->where('status', 'held')
            ->whereHas('reservation', fn ($r) => $r->withoutGlobalScope('tenant_isolation')
                ->where('user_id', $attempt->user_id)
                ->where('payment_model', 'direct_owner_v1')
                ->where('status', 'held'));

        if ($target !== null && $target['invoice_id'] !== null) {
            $invoice = MonthlyInvoice::query()->withoutGlobalScope('tenant_isolation')
                ->where('recurring_series_id', $target['series_id'])->find($target['invoice_id']);
            if (! $invoice) {
                return $query->whereRaw('1 = 0');
            }
            $query->whereBetween('starts_at', [
                Carbon::parse($invoice->period_start)->startOfDay(),
                Carbon::parse($invoice->period_end)->endOfDay(),
            ]);
        }

        return $query;
    }

    /** Whether a verified 'recurring_period' attempt still has held occurrences it can confirm. */
    public function hasConfirmablePeriod(OwnerPaymentAttempt $attempt): bool
    {
        $target = $this->parseAttemptTarget((string) $attempt->purpose_id);
        if ($target === null || $attempt->purpose !== 'recurring_period' || $attempt->payment_model !== 'direct_owner_v1') {
            return false;
        }

        $series = RecurringSeries::query()->withoutGlobalScope('tenant_isolation')->find($target['series_id']);
        if (! $series
            || (int) $series->organization_id !== (int) $attempt->organization_id
            || (int) $series->user_id !== (int) $attempt->user_id
            || (int) $series->court()->value('venue_id') !== (int) $attempt->venue_id
            || ! data_get($series->metadata, 'accepted_snapshot')) {
            return false;
        }

        $invoices = MonthlyInvoice::query()->withoutGlobalScope('tenant_isolation')
            ->where('recurring_series_id', $series->id)
            ->where('organization_id', $attempt->organization_id)
            ->when($target['invoice_id'] !== null, fn ($q) => $q->whereKey($target['invoice_id']))
            ->get();

        return $invoices->isNotEmpty()
            && $invoices->every(fn (MonthlyInvoice $invoice) => $invoice->status === 'pending')
            && (int) $invoices->sum('amount') === (int) $attempt->amount
            && (int) $this->confirmableOccurrencesQuery($attempt)->sum('amount') === (int) $attempt->amount;
    }

    /**
     * Settle invoices only from the allocated, verified owner receipt. The payment inbox holds the
     * attempt lock and calls this inside the same transaction as receipt/allocation persistence.
     */
    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, CarbonInterface $paidAt): void
    {
        $attempt = $allocation->attempt;
        $receipt = $allocation->receipt;
        abort_unless(
            $attempt && $receipt
                && $attempt->status === OwnerPaymentAttempt::STATUS_PAID
                && $attempt->payment_model === 'direct_owner_v1'
                && $receipt->status === OwnerPaymentReceipt::STATUS_ALLOCATED
                && $receipt->receiving_connection_evidence_id !== null
                && $receipt->transfer_type === 'in'
                && (int) $receipt->payment_attempt_id === (int) $attempt->id
                && (int) $receipt->receiving_connection_id === (int) $attempt->receiving_connection_id
                && (int) $receipt->organization_id === (int) $attempt->organization_id
                && (int) $allocation->organization_id === (int) $attempt->organization_id
                && (int) $allocation->payment_attempt_id === (int) $attempt->id
                && (int) $allocation->receiving_connection_id === (int) $attempt->receiving_connection_id
                && (int) $allocation->amount === (int) $attempt->amount
                && (int) $receipt->amount === (int) $attempt->amount
                && $allocation->currency === $attempt->currency
                && $receipt->currency === $attempt->currency
                && $allocation->purpose === $attempt->purpose
                && $allocation->purpose_id === $attempt->purpose_id
                && $receipt->event_at?->equalTo($paidAt),
            409, 'A verified owner receipt allocation is required for an invoice.'
        );

        $target = $this->parseAttemptTarget((string) $attempt->purpose_id);
        $series = RecurringSeries::query()->withoutGlobalScope('tenant_isolation')
            ->whereKey($target['series_id'] ?? 0)->lockForUpdate()->firstOrFail();
        abort_unless($this->hasConfirmablePeriod($attempt), 409, 'This recurring period is no longer payable.');

        $invoices = MonthlyInvoice::query()->withoutGlobalScope('tenant_isolation')
            ->where('recurring_series_id', $series->id)
            ->when($target['invoice_id'] !== null, fn ($q) => $q->whereKey($target['invoice_id']))
            ->lockForUpdate()->get();

        foreach ($this->confirmableOccurrencesQuery($attempt)->lockForUpdate()->get() as $occurrence) {
            $reservation = $occurrence->reservation()->withoutGlobalScope('tenant_isolation')->lockForUpdate()->firstOrFail();
            $this->confirmReservationInline($reservation, $paidAt);
            $occurrence->forceFill(['status' => 'confirmed'])->save();
        }

        foreach ($invoices as $invoice) {
            abort_unless($invoice->status === 'pending', 409, 'This invoice is no longer payable.');
            $invoice->forceFill(['status' => 'paid', 'payment_reference' => $attempt->reference, 'paid_at' => $paidAt])->save();
        }

        $stillHeld = $series->occurrences()->withoutGlobalScope('tenant_isolation')->where('status', 'held')->exists();
        $series->forceFill(['status' => $stillHeld ? 'pending' : 'confirmed'])->save();
    }

    public function markOverdueAndReleaseFuture(): int
    {
        $released = 0;
        MonthlyInvoice::query()->where('status', 'pending')->where('due_at', '<', now())->orderBy('id')->each(function (MonthlyInvoice $invoice) use (&$released): void {
            DB::transaction(function () use ($invoice, &$released): void {
                $locked = MonthlyInvoice::query()->whereKey($invoice->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'pending') {
                    return; // A concurrent payment settled this invoice before the sweep locked it.
                }
                $series = $locked->series;
                // Grace beyond due_at: captured on accept for flag-ON series, 0 for legacy series.
                $graceHours = (int) data_get($series?->metadata ?? [], 'accepted_snapshot.overdue_release_hours', 0);
                if (Carbon::parse($locked->due_at)->addHours(max(0, $graceHours))->isFuture()) {
                    return; // Still inside the grace window; release only after the period deadline.
                }
                $locked->update(['status' => 'overdue', 'overdue_at' => now()]);
                if (! $series) {
                    return;
                }
                // Release only this period's still-unpaid FUTURE occurrences; paid/past ones stay.
                $occurrences = $series->occurrences()
                    ->whereBetween('starts_at', [
                        Carbon::parse($locked->period_start)->startOfDay(),
                        Carbon::parse($locked->period_end)->endOfDay(),
                    ])
                    ->where('starts_at', '>', now())
                    ->whereIn('status', ['held', 'confirmed'])
                    ->lockForUpdate()
                    ->get();
                $released += $this->releases->releaseOccurrences($occurrences);
                // Expire the period's still-pending payment attempt(s) so no stale hold lingers.
                $this->releases->expirePendingAttempts($series->id, [$locked->id]);
                $series->update(['status' => $series->status === 'cancelled' ? 'cancelled' : 'delinquent']); // An admin cancel stays final.
            });
        });

        return $released;
    }

    public function notifyUpcomingInvoices(): int
    {
        $count = 0;
        MonthlyInvoice::query()->with('series.user')->where('status', 'pending')->whereNull('reminder_sent_at')->whereBetween('due_at', [now()->addDays(7), now()->addDays(8)])->each(function (MonthlyInvoice $invoice) use (&$count): void {
            $invoice->update(['reminder_sent_at' => now()]);
            $this->outbox->enqueue([
                'organization_id' => $invoice->organization_id,
                'message_key' => "recurring-invoice:{$invoice->id}:reminder",
                'channel' => 'notification',
                'event_type' => 'recurring.invoice_due',
                'aggregate_type' => MonthlyInvoice::class,
                'aggregate_id' => $invoice->id,
            ], ['user_id' => $invoice->series->user_id, 'notification' => ['event' => 'recurring_invoice_due', 'invoice_id' => $invoice->id, 'amount' => $invoice->amount]]);
            $count++;
        });

        return $count;
    }

    /**
     * Read-only preview: prices every occurrence over its full session interval and flags
     * true-interval overlaps against active inventory without creating any hold.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewSeries(User $user, array $data): array
    {
        $court = Court::with(['venue', 'sportZone'])->findOrFail((int) $data['court_id']);

        $frequency = strtolower((string) ($data['frequency'] ?? ''));
        if (! in_array($frequency, ['weekly', 'biweekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['frequency' => 'Frequency must be weekly, biweekly or monthly.']);
        }
        $firstStart = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone'))->setSecond(0);
        $firstEnd = isset($data['ends_at']) ? Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone'))->setSecond(0) : $firstStart->copy()->addHour();
        $untilProvided = isset($data['until']);
        $until = $untilProvided ? Carbon::parse($data['until'])->setTimezone(config('app.timezone'))->setSecond(0) : null;
        $untilClamped = $until !== null && $until->greaterThan($firstStart->copy()->addMonths(12));
        $weekdays = $this->normalizeWeekdays($data['weekdays'] ?? []);
        if ($frequency !== 'monthly' && $weekdays === []) {
            $weekdays = [(int) $firstStart->isoWeekday()];
        }
        $requested = (int) ($data['occurrences'] ?? 0);
        $requested = $requested > 0 ? $requested : null;

        $full = $this->generateOccurrences($court, $frequency, $weekdays, $firstStart, $firstEnd, $until, null);
        $hitCap = count($full) >= 104;
        if ($requested !== null && $requested < count($full)) {
            $final = array_slice($full, 0, $requested);
            $truncatedBy = 'count';
        } else {
            $final = $full;
            $truncatedBy = ($hitCap || $untilClamped) ? 'cap' : ($untilProvided ? 'until' : null);
        }

        $sessionMinutes = (int) abs($firstStart->diffInMinutes($firstEnd));
        $slotMinutes = (int) ($court->sportZone?->slot_duration ?: 60);
        // Validate divisibility up front so preview rejects the same sessions create would.
        $this->sessionSlotStarts($court, $firstStart, $sessionMinutes);
        $occurrences = [];
        $total = 0;
        foreach ($final as $index => $occurrence) {
            $start = $occurrence['starts_at'];
            $end = $occurrence['ends_at'];
            // Price every sub-interval of the session and sum, mirroring the persisted quote.
            $snapshot = $this->quotes->priceSnapshots($court, $this->sessionSlotStarts($court, $start, $sessionMinutes), $slotMinutes, true);
            $price = (int) collect($snapshot)->sum('unit_price');
            $total += $price;
            $reason = $this->overlapReason($court, $start, $end);
            $occurrences[] = [
                'index' => $index + 1,
                'starts_at' => $start->copy()->utc()->toIso8601String(),
                'ends_at' => $end->copy()->utc()->toIso8601String(),
                'weekday' => $occurrence['weekday'],
                'price' => $price,
                'conflict' => $reason !== null,
                'conflict_reason' => $reason,
            ];
        }

        return [
            'frequency' => $frequency,
            'weekdays' => $frequency === 'monthly' ? [] : array_values($weekdays),
            'count' => count($occurrences),
            'currency' => 'VND',
            'total_amount' => $total,
            'occurrences' => $occurrences,
            'truncated_by' => $truncatedBy,
        ];
    }

    /**
     * Single source of truth for recurrence dates, shared by preview and create so the
     * persisted series always equals the accepted preview. Expands selected weekdays for
     * weekly/biweekly (anchored on the week containing $startDateTime); monthly ignores
     * weekdays and steps the same day-of-month. Bounded by $occurrences, $until (default
     * +12 months) and a hard cap of 104 occurrences / 12 months.
     *
     * @param  array<int, int>  $weekdaysIso  ISO weekdays 1..7
     * @return array<int, array{starts_at: Carbon, ends_at: Carbon, weekday: int}>
     */
    public function generateOccurrences(Court $court, string $frequency, array $weekdaysIso, Carbon $startDateTime, Carbon $endDateTime, ?Carbon $until, ?int $occurrences): array
    {
        $sessionMinutes = (int) abs($startDateTime->diffInMinutes($endDateTime));
        $hardHorizon = $startDateTime->copy()->addMonths(12);
        $effectiveUntil = $until !== null && $until->lessThan($hardHorizon) ? $until->copy() : $hardHorizon;
        $cap = $occurrences !== null ? min($occurrences, 104) : 104;

        $result = [];
        if ($frequency === 'monthly') {
            $current = $startDateTime->copy();
            while ($current->lessThanOrEqualTo($effectiveUntil) && count($result) < $cap) {
                $result[] = $this->makeOccurrence($current, $sessionMinutes);
                $current = $current->copy()->addMonthNoOverflow();
            }

            return $result;
        }

        $weekdays = collect($weekdaysIso === [] ? [(int) $startDateTime->isoWeekday()] : $weekdaysIso)
            ->sort()->values()->all();
        $stepWeeks = $frequency === 'biweekly' ? 2 : 1;
        $anchorMonday = $startDateTime->copy()->startOfWeek(Carbon::MONDAY);
        $weekIndex = 0;
        while (count($result) < $cap) {
            $weekMonday = $anchorMonday->copy()->addWeeks($weekIndex * $stepWeeks);
            if ($weekMonday->greaterThan($effectiveUntil)) {
                break;
            }
            foreach ($weekdays as $iso) {
                if (count($result) >= $cap) {
                    break;
                }
                $candidate = $weekMonday->copy()->addDays($iso - 1)->setTimeFrom($startDateTime);
                if ($candidate->lessThan($startDateTime) || $candidate->greaterThan($effectiveUntil)) {
                    continue;
                }
                $result[] = $this->makeOccurrence($candidate, $sessionMinutes);
            }
            $weekIndex++;
            if ($weekIndex > 260) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return array{starts_at: Carbon, ends_at: Carbon, weekday: int}
     */
    private function makeOccurrence(Carbon $start, int $sessionMinutes): array
    {
        $s = $start->copy();

        return [
            'starts_at' => $s,
            'ends_at' => $s->copy()->addMinutes($sessionMinutes),
            'weekday' => (int) $s->isoWeekday(),
        ];
    }

    /**
     * Enumerate every sub-interval start (ISO8601) within a single session, stepping by the
     * court's zone slot_duration so a multi-hour session reserves/prices EVERY interval, not
     * just the first. The session length MUST be a positive integer multiple of the slot
     * duration and MUST NOT exceed the 24-slot per-quote cap.
     *
     * @return array<int, string>
     */
    private function sessionSlotStarts(Court $court, Carbon $start, int $sessionMinutes): array
    {
        $court->loadMissing('sportZone');
        $slotMinutes = (int) ($court->sportZone?->slot_duration ?: 60);
        if ($slotMinutes < 1 || $sessionMinutes < 1 || $sessionMinutes % $slotMinutes !== 0) {
            throw ValidationException::withMessages(['ends_at' => 'Độ dài buổi phải là bội số của độ dài slot sân.']);
        }
        $count = intdiv($sessionMinutes, $slotMinutes);
        if ($count > 24) {
            throw ValidationException::withMessages(['ends_at' => 'Một buổi định kỳ không được vượt quá 24 slot.']);
        }

        $starts = [];
        for ($i = 0; $i < $count; $i++) {
            $starts[] = $start->copy()->addMinutes($i * $slotMinutes)->toIso8601String();
        }

        return $starts;
    }

    /**
     * @param  mixed  $weekdays
     * @return array<int, int>
     */
    private function normalizeWeekdays($weekdays): array
    {
        return collect(is_array($weekdays) ? $weekdays : [])
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function overlapReason(Court $court, Carbon $start, Carbon $end): ?string
    {
        $startUtc = $start->copy()->utc();
        $endUtc = $end->copy()->utc();

        $v1Conflict = BookingSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->whereHas('booking', fn ($booking) => $booking->whereIn('status', ['confirmed', 'pending_payment']))
            ->exists();

        $v2Conflict = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->whereIn('status', ['held', 'confirmed'])
            ->whereHas('reservation', fn ($reservation) => $reservation->whereIn('status', ['held', 'confirmed']))
            ->exists();

        return ($v1Conflict || $v2Conflict) ? 'court_time_conflict' : null;
    }

    private function createInvoices(RecurringSeries $series): void
    {
        $groups = $series->occurrences->groupBy(fn (RecurringOccurrence $occurrence) => $occurrence->starts_at->format('Y-m'));
        foreach ($groups as $month => $occurrences) {
            $periodStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            MonthlyInvoice::create([
                'organization_id' => $series->organization_id,
                'recurring_series_id' => $series->id,
                'period_start' => $periodStart,
                'period_end' => $periodStart->copy()->endOfMonth(),
                'due_at' => $periodStart->copy()->subDays(7),
                'amount' => $occurrences->sum('amount'),
                'status' => 'pending',
            ]);
        }
    }
}

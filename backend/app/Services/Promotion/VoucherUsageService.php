<?php

namespace App\Services\Promotion;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Contracts\Repositories\VoucherUseRepositoryInterface;
use App\Domain\Pricing\DTOs\RentalQuote;
use App\Domain\Pricing\DTOs\VoucherChoice;
use App\Exceptions\VoucherNoLongerApplies;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\VoucherUse;
use App\Services\Pricing\RentalPriceCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atomic voucher use (design D11): a use is held when a booking pending payment is created, used when it is
 * confirmed and released once when it ends unconfirmed. The voucher_uses rows are the counts, so held + used never
 * exceeds max_uses and a private code has at most one held or used row. hold and consume run in a savepoint of their
 * caller's transaction (on their own, the program lock would end before the write); each method is idempotent per
 * reservation. Lock order: court → venue → attempt → reservation → program.
 */
class VoucherUsageService
{
    public function __construct(
        private readonly VoucherEligibility $eligibility,
        private readonly RentalPriceCalculator $rentals,
        private readonly PromotionProgramRepositoryInterface $programs,
        private readonly VoucherUseRepositoryInterface $uses,
        private readonly ReservationRepositoryInterface $reservations,
    ) {}

    /**
     * Holds one use for a booking inserted with its slots. The caller already locked the program FOR UPDATE before
     * pricing (D17); locking it again here is a no-op in that transaction. The voucher is judged now for the
     * booking's court and rental; VoucherNoLongerApplies with the D20 reason otherwise. Repeating it changes nothing.
     */
    public function hold(Reservation $reservation, VoucherChoice $choice): void
    {
        DB::transaction(function () use ($reservation, $choice): void {
            if ($this->uses->forReservation((int) $reservation->id) !== null) {
                return;
            }
            $court = $this->courtOf($reservation);
            $base = $this->rentalOf($reservation, $court, now());
            [$program, $code] = $this->eligibility->resolve($court, $choice, forUpdate: true) ?? throw new VoucherNoLongerApplies('not_found', $base);
            $quote = $this->eligibility->check($program, $code, $court, $base, now(), $reservation);

            $use = $this->uses->create([
                'reservation_id' => $reservation->id,
                'program_id' => $program->id,
                'voucher_code_id' => $code?->id,
                'status' => VoucherUse::STATUS_HELD,
                'discount' => $quote->discount,
                'held_at' => now(),
            ]);
            if ($use === null && $this->uses->forReservation((int) $reservation->id) === null) {
                // Only the partial unique index on the code is left to refuse it: another booking took the code.
                $other = $code === null ? null : $this->uses->activeForCode((int) $code->id);
                throw new VoucherNoLongerApplies($other?->status === VoucherUse::STATUS_USED ? 'used' : 'held_elsewhere', $base);
            }
        });
    }

    /** The booking's held use → released, exactly once; locks neither program nor code. No-op without a held use. */
    public function release(Reservation $reservation): void
    {
        $this->uses->releaseHeld((int) $reservation->id, now());
    }

    /**
     * Turns the booking's held use into a used one, after rechecking it as D19 under the program's FOR SHARE lock
     * (no venue lock; callers that need one take it first, D11): the program not stopped, its window judged at
     * $confirmedAt, the booking's current court and its unreleased slots priced as confirmed at $confirmedAt (D6),
     * its own use counted as its own, and the discount equal to the one recorded. discount_changed when that discount
     * cannot be confirmed: it differs, the time lost its price, or the use stopped being held meanwhile. used_at is
     * $confirmedAt, the time module 4 records as confirmed_at. On failure VoucherNoLongerApplies, nothing changed.
     * No-op without a held use.
     */
    public function consume(Reservation $reservation, CarbonInterface $confirmedAt): void
    {
        DB::transaction(function () use ($reservation, $confirmedAt): void {
            $use = $this->heldUse($reservation);
            if ($use === null) {
                return;
            }
            $program = $this->programs->findForShare((int) $use->program_id);
            $court = $this->courtOf($reservation);
            try {
                $base = $this->rentalOf($reservation, $court, $confirmedAt);
            } catch (ValidationException) {
                throw new VoucherNoLongerApplies('discount_changed', $this->recordedRental($reservation));
            }
            $quote = $this->eligibility->check($program, $use->voucherCode, $court, $base, $confirmedAt, $reservation);
            if ($quote->discount !== (int) $use->discount) {
                throw new VoucherNoLongerApplies('discount_changed', $base);
            }

            if (! $this->uses->markUsed($use, $confirmedAt) && $this->uses->forReservation((int) $reservation->id)?->status !== VoucherUse::STATUS_USED) {
                // Released under us (a caller that skipped the reservation lock): the booking no longer holds it.
                throw new VoucherNoLongerApplies('discount_changed', $base);
            }
        });
    }

    public function holdsVoucher(Reservation $reservation): bool
    {
        return $this->heldUse($reservation) !== null;
    }

    /** The choice behind the booking's held use, to price it again with $heldBy = the booking (D17, D19). */
    public function choiceFor(Reservation $reservation): ?VoucherChoice
    {
        $use = $this->heldUse($reservation);
        if ($use === null) {
            return null;
        }

        return $use->voucherCode === null ? VoucherChoice::program((int) $use->program_id) : VoucherChoice::code($use->voucherCode->code);
    }

    private function heldUse(Reservation $reservation): ?VoucherUse
    {
        $use = $this->uses->forReservation((int) $reservation->id);

        return $use?->status === VoucherUse::STATUS_HELD ? $use : null;
    }

    /** Read by id, not through a loaded relation that may predate a court change. */
    private function courtOf(Reservation $reservation): Court
    {
        return $reservation->court()->firstOrFail();
    }

    /**
     * The booking's rental without a voucher if confirmed at $confirmedAt: one engine call per run of adjacent
     * unreleased slots within one local day, so each run drops its fraction once (D5, D29). A booking on the
     * 30-minute grid has a single slot.
     */
    private function rentalOf(Reservation $reservation, Court $court, CarbonInterface $confirmedAt): RentalQuote
    {
        $timezone = $court->loadMissing('venue')->venue?->timezone ?: config('app.timezone');
        $runs = [];
        foreach ($this->reservations->unreleasedSlots((int) $reservation->id) as $slot) {
            $start = CarbonImmutable::instance($slot->starts_at)->setTimezone($timezone);
            $end = CarbonImmutable::instance($slot->ends_at)->setTimezone($timezone);
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last][1]->eq($start) && $runs[$last][0]->isSameDay($start)) {
                $runs[$last][1] = $end;
            } else {
                $runs[] = [$start, $end];
            }
        }

        $rental = 0;
        $parts = [];
        foreach ($runs as [$start, $end]) {
            $quote = $this->rentals->rentalConfirmedAt($court, $start, $end, $confirmedAt);
            $rental += $quote->rental;
            $parts = [...$parts, ...$quote->snapshot['parts']];
        }

        return new RentalQuote(rental: $rental, discount: 0, total: $rental, snapshot: ['parts' => $parts, 'voucher' => null]);
    }

    /** The rental recorded on the booking's unreleased slots, for the 422 body when its time can no longer be priced. */
    private function recordedRental(Reservation $reservation): RentalQuote
    {
        $rental = (int) $this->reservations->unreleasedSlots((int) $reservation->id)->sum('unit_price');

        return new RentalQuote(rental: $rental, discount: 0, total: $rental, snapshot: ['parts' => [], 'voucher' => null]);
    }
}

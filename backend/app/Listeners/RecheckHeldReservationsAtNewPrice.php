<?php

namespace App\Listeners;

use App\Events\CourtPricesTookEffect;
use App\Exceptions\VoucherNoLongerApplies;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Services\Booking\WaitlistService;
use App\Services\Pricing\RentalPriceCalculator;
use App\Services\Promotion\VoucherUsageService;
use App\Services\Reservation\HeldReservationRelease;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-87 (design D11): when new prices take effect, every booking pending payment on those courts that ends after
 * that time is priced again. A different amount payable expires it as a price change and tells the customer to
 * submit again; the same amount keeps it and its payment deadline. One transaction per booking, so one failure
 * rolls back no other and no two bookings are locked together.
 */
class RecheckHeldReservationsAtNewPrice
{
    public function __construct(
        private readonly HeldReservationRelease $release,
        private readonly RentalPriceCalculator $rentals,
        private readonly WaitlistService $waitlists,
        private readonly VoucherUsageService $vouchers,
    ) {}

    public function handle(CourtPricesTookEffect $event): void
    {
        $candidates = Reservation::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('status', 'held')
            // A recurring occurrence keeps the price of its contract and monthly invoice.
            ->whereDoesntHave('recurringOccurrence')
            ->whereHas('slots', fn ($slots) => $slots->whereIn('court_id', $event->courtIds)->where('ends_at', '>', $event->effectiveAt->utc()))
            ->orderBy('id')
            ->get(['id', 'code']);
        foreach ($candidates as $candidate) {
            rescue(fn () => DB::transaction(fn () => $this->recheck($candidate)));
        }
    }

    private function recheck(Reservation $candidate): void
    {
        $locked = $this->release->lock($candidate);
        if (! $locked || $locked->status !== 'held') {
            return;
        }

        $slots = ReservationSlot::query()->withoutGlobalScope('tenant_isolation')->where('reservation_id', $locked->id)->get();
        if ($this->payable($locked, $slots) === (int) $locked->total_amount
            || ! $this->release->releaseHeld($locked, 'expired', 'price_changed')) {
            return;
        }

        $this->release->notifyReleased($locked, 'price_changed');
        DB::afterCommit(fn () => $slots->each(fn ($slot) => $this->waitlists->notifyForSlot((int) $slot->court_id, $slot->starts_at)));
    }

    /**
     * What the booking would pay now with the voucher it holds, or null when its time has no price any more or the
     * voucher no longer applies.
     *
     * @param  Collection<int, ReservationSlot>  $slots
     */
    private function payable(Reservation $locked, Collection $slots): ?int
    {
        $court = Court::query()->withoutGlobalScope('tenant_isolation')->findOrFail($locked->court_id);
        try {
            // One quote for the whole booking, so the voucher's minimum and discount apply to it as at checkout (D19).
            return $this->rentals->quote(
                $court,
                CarbonImmutable::parse($slots->min('starts_at')),
                CarbonImmutable::parse($slots->max('ends_at')),
                $this->vouchers->choiceFor($locked),
                $locked,
            )->total;
        } catch (ValidationException|VoucherNoLongerApplies) {
            return null;
        }
    }
}

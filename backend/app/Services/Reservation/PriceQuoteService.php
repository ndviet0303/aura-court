<?php

namespace App\Services\Reservation;

use App\Domain\Pricing\DTOs\RentalQuote;
use App\Domain\Pricing\DTOs\VoucherChoice;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Exceptions\VoucherNoLongerApplies;
use App\Models\Court;
use App\Models\PriceQuote;
use App\Models\ReservationSlot;
use App\Services\Pricing\RentalPriceCalculator;
use App\Services\Promotion\VoucherEligibility;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PriceQuoteService
{
    public function __construct(
        private readonly VenueOperatingCalendar $operatingCalendar,
        private readonly RentalPriceCalculator $rentals,
        private readonly ReservationIntake $intake,
        private readonly VoucherEligibility $vouchers,
        private readonly SlotSelectionPolicy $slotPolicy,
    ) {}

    /** A customer's quote: one interval on the 30-minute grid, priced and later stored as one slot (design D14, D15). */
    public function create(int $userId, int $organizationId, int $courtId, mixed $startsAt, mixed $endsAt): PriceQuote
    {
        $court = app(VerifyEffectiveBookingEligibility::class)->assertEligible($courtId, publicFacing: true);
        [$start, $end] = $this->intake->bookingInterval($court, $startsAt, $endsAt);

        return $this->open($userId, $organizationId, $court, collect([Carbon::instance($start)]), intdiv($end->getTimestamp() - $start->getTimestamp(), 60));
    }

    /** @param  Collection<int, Carbon>  $resolvedSlots */
    private function open(int $userId, int $organizationId, Court $court, Collection $resolvedSlots, int $duration): PriceQuote
    {
        if ($court->venue?->organization_id) {
            $organizationId = (int) $court->venue->organization_id;
        }

        foreach ($resolvedSlots as $time) {
            if (! $this->operatingCalendar->isOpen($court, $time, $time->copy()->addMinutes($duration))) {
                throw ValidationException::withMessages(['slots' => 'Khung giờ đã chọn nằm ngoài lịch hoạt động của sân.']);
            }
        }

        $firstSlot = $resolvedSlots->first();
        $lastSlot = $resolvedSlots->last();
        $blockStarts = $firstSlot->copy()->utc();
        $blockEnds = $lastSlot->copy()->addMinutes($duration)->utc();
        $this->slotPolicy->assertNoStop($court, $blockStarts, $blockEnds);

        $overlapping = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $blockEnds)
            ->where('ends_at', '>', $blockStarts)
            ->whereHas('reservation', fn ($r) => $r->blocksInventory())
            ->exists();

        if ($overlapping) {
            abort(409, 'The selected court-time is no longer available.');
        }

        return PriceQuote::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'court_id' => $court->id,
            'quote_token' => (string) Str::uuid(),
            'status' => 'open',
            'currency' => 'VND',
            ...$this->priceAttributes($court, $resolvedSlots->all(), $duration),
            'expires_at' => now()->addMinutes(15),
        ])->load(['court.venue']);
    }

    /**
     * Price an open quote's selection again at submission (design D5, D6), so the booking is created from today's
     * amounts and the caller can compare them with what the customer was shown.
     */
    public function reprice(PriceQuote $quote, Court $court): void
    {
        $slots = collect((array) $quote->slot_snapshot);
        $times = $slots->map(fn (array $slot): Carbon => Carbon::parse($slot['starts_at']))->all();
        // A quote's one slot is the whole interval of the booking.
        $duration = intdiv(Carbon::parse($slots->first()['ends_at'])->getTimestamp() - $times[0]->getTimestamp(), 60);

        $quote->forceFill($this->priceAttributes($court, $times, $duration))->save();
    }

    /**
     * reprice() for a booking that names a voucher (design D11, D17, D20): the program is locked FOR UPDATE first,
     * after releaseOverdueHolds, so its terms and uses stay put until commit; the quote then totals T − D. Judged
     * before any amount is compared: VoucherNoLongerApplies with the voucher-less amounts when it does not apply.
     */
    public function repriceWithVoucher(PriceQuote $quote, Court $court, VoucherChoice $choice): RentalQuote
    {
        $resolved = $this->vouchers->resolve($court, $choice, forUpdate: true);
        $this->reprice($quote, $court);
        $rental = (int) $quote->subtotal;
        $parts = collect((array) $quote->slot_snapshot)->flatMap(fn (array $slot): array => $slot['parts'] ?? [])->values()->all();
        $base = new RentalQuote(rental: $rental, discount: 0, total: $rental, snapshot: ['parts' => $parts, 'voucher' => null]);
        [$program, $code] = $resolved ?? throw new VoucherNoLongerApplies('not_found', $base);
        $applied = $this->vouchers->check($program, $code, $court, $base, now());
        $quote->forceFill(['total_amount' => $applied->total])->save();

        return $applied;
    }

    /**
     * @param  array<int, Carbon>  $times
     * @return array{subtotal: int, total_amount: int, benefit_snapshot: null, slot_snapshot: array}
     */
    private function priceAttributes(Court $court, array $times, int $duration): array
    {
        $total = (int) collect($snapshot = $this->priceSnapshots($court, $times, $duration))->sum('unit_price');

        // No coupon or membership discount on court bookings until vouchers (design D28).
        return ['subtotal' => $total, 'total_amount' => $total, 'benefit_snapshot' => null, 'slot_snapshot' => $snapshot];
    }

    /**
     * Each slot [start, start + duration) priced by the engine. A run of adjacent slots within one local day is
     * priced once, so its fraction is dropped once (design D5, D29), and its last slot takes what the others leave.
     *
     * @param  array<int, mixed>  $times
     * @return list<array{court_id: int, starts_at: string, ends_at: string, unit_price: int, parts: list<array<string, mixed>>}>
     */
    public function priceSnapshots(Court $court, array $times, ?int $duration = null): array
    {
        $court->loadMissing(['venue']);
        $duration ??= (int) $court->slot_duration;
        $timezone = $court->venue?->timezone ?: config('app.timezone');
        $runs = [];
        foreach (collect($times)->map(fn ($value): CarbonImmutable => $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance($value)->setTimezone($timezone)
            : CarbonImmutable::parse((string) $value, $timezone))->sort() as $start) {
            $run = array_key_last($runs);
            $previous = $run === null ? null : $runs[$run][array_key_last($runs[$run])];
            if ($previous && $previous->addMinutes($duration)->eq($start) && $start->isSameDay($runs[$run][0])) {
                $runs[$run][] = $start;
            } else {
                $runs[] = [$start];
            }
        }

        $snapshot = [];
        foreach ($runs as $run) {
            $slots = array_map(fn (CarbonImmutable $start): array => [$start, $start->addMinutes($duration)], $run);
            $runEnd = $slots[array_key_last($slots)][1];
            $quotes = $this->rentals->quoteEach($court, [[$run[0], $runEnd], ...$slots]);
            // quote() refuses with the first unpriced minute.
            $left = ($quotes[0] ?? $this->rentals->quote($court, $run[0], $runEnd, null))->rental;
            foreach ($slots as $index => [$start, $end]) {
                $quote = $quotes[$index + 1] ?? $this->rentals->quote($court, $start, $end, null);
                $price = $index === array_key_last($slots) ? $left : $quote->rental;
                $left -= $price;
                $snapshot[] = [
                    'court_id' => (int) $court->id,
                    'starts_at' => $start->utc()->toIso8601String(),
                    'ends_at' => $end->utc()->toIso8601String(),
                    'unit_price' => $price,
                    'parts' => $quote->snapshot['parts'],
                ];
            }
        }

        return $snapshot;
    }
}

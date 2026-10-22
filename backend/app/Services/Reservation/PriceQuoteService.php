<?php

namespace App\Services\Reservation;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Models\PriceQuote;
use App\Models\ReservationSlot;
use App\Services\Pricing\RentalPriceCalculator;
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
        private readonly SlotSelectionPolicy $slotPolicy,
    ) {}

    public function create(int $userId, int $organizationId, int $courtId, array $slots): PriceQuote
    {
        $eligibility = app(VerifyEffectiveBookingEligibility::class);
        $court = $eligibility->assertEligible($courtId, publicFacing: true);
        if ($court->venue?->organization_id) {
            $organizationId = (int) $court->venue->organization_id;
        }

        $duration = (int) $court->slot_duration;
        $timezone = $court->venue?->timezone ?: config('app.timezone');
        $resolvedSlots = $this->resolveSlots($slots, $duration, $timezone);
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
        $times = collect((array) $quote->slot_snapshot)->map(fn (array $slot): Carbon => Carbon::parse($slot['starts_at']))->all();

        $quote->forceFill($this->priceAttributes($court, $times, (int) $court->slot_duration))->save();
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

    /** @return Collection<int,Carbon> */
    private function resolveSlots(array $slots, int $duration = 60, ?string $zone = null): Collection
    {
        if (count($slots) < 1 || count($slots) > 24) {
            throw ValidationException::withMessages(['slots' => 'Chọn từ 1 đến 24 giờ.']);
        }

        $zone ??= config('app.timezone');
        $local = collect($slots)->map(function (mixed $value) use ($zone): Carbon {
            $value = is_array($value) ? ($value['starts_at'] ?? null) : $value;
            try {
                $time = Carbon::parse((string) $value)->setTimezone($zone);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['slots' => 'Giờ bắt đầu không hợp lệ.']);
            }

            if ($time->minute % 30 !== 0 || $time->second !== 0) {
                throw ValidationException::withMessages(['slots' => 'Giờ bắt đầu phải theo mốc 30 phút.']);
            }
            if ($time->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể báo giá giờ đã qua.']);
            }

            return $time;
        })->sort()->values();

        if ($local->unique(fn (Carbon $time): string => $time->toIso8601String())->count() !== $local->count()) {
            throw ValidationException::withMessages(['slots' => 'Không được chọn trùng giờ.']);
        }

        return $local;
    }
}

<?php

namespace App\Services\Pricing;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Domain\Pricing\DTOs\RentalQuote;
use App\Domain\Pricing\DTOs\VoucherChoice;
use App\Models\Court;
use App\Models\PriceTable;
use App\Models\Reservation;
use App\Services\Promotion\VoucherEligibility;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * The one rental calculation (design D5, D17): [startsAt, endsAt) of a court within one local day is split
 * wherever the price per hour changes, and charged T = ⌊Σ minutes × price per hour / 60⌋ whole VND.
 */
class RentalPriceCalculator
{
    public function __construct(
        private readonly PriceTableRepositoryInterface $priceTables,
        private readonly VoucherEligibility $vouchers,
    ) {}

    /**
     * The amounts a booking confirmed now would pay. 422 when the interval leaves its local day or any of
     * its minutes has no price; no other price stands in for it. A voucher is judged now, as when it is listed,
     * entered or submitted (D17), and one that does not apply throws VoucherNoLongerApplies with the voucher-less
     * amounts; the use or code $heldBy holds is not counted against it.
     */
    public function quote(Court $court, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?VoucherChoice $voucher, ?Reservation $heldBy = null): RentalQuote
    {
        $quote = $this->price($court, $startsAt, $endsAt, $this->timeline($court, $startsAt, $endsAt, now()));

        return $voucher === null ? $quote : $this->vouchers->apply($court, $voucher, $quote, now(), $heldBy);
    }

    /** quote() without a voucher for a booking confirmed at $confirmedAt (D6): versions published later do not count. */
    public function rentalConfirmedAt(Court $court, CarbonImmutable $startsAt, CarbonImmutable $endsAt, CarbonInterface $confirmedAt): RentalQuote
    {
        return $this->price($court, $startsAt, $endsAt, $this->timeline($court, $startsAt, $endsAt, $confirmedAt));
    }

    /**
     * quote() without a voucher for many intervals of one court, reading its prices once. Keyed like $intervals;
     * an interval quote() would refuse is left out.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     * @return array<int, RentalQuote>
     */
    public function quoteEach(Court $court, array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }
        $timeline = $this->timeline($court, min(array_column($intervals, 0)), max(array_column($intervals, 1)), now());
        $quotes = [];
        foreach ($intervals as $key => [$startsAt, $endsAt]) {
            try {
                $quotes[$key] = $this->price($court, $startsAt, $endsAt, $timeline);
            } catch (ValidationException) {
                // Unpriced or leaving its day: not offered.
            }
        }

        return $quotes;
    }

    private function timeline(Court $court, CarbonImmutable $from, CarbonImmutable $until, CarbonInterface $confirmedAt): CourtPriceTimeline
    {
        return new CourtPriceTimeline($this->priceTables->linksForCourts([(int) $court->id], $from, $until), $confirmedAt);
    }

    private function price(Court $court, CarbonImmutable $startsAt, CarbonImmutable $endsAt, CourtPriceTimeline $timeline): RentalQuote
    {
        $start = $startsAt->setTimezone($court->loadMissing('venue')->venue?->timezone ?: config('app.timezone'));
        $day = $start->startOfDay();
        $minuteOf = fn (CarbonImmutable $time): int => (int) round($day->diffInMinutes($time));
        if ($endsAt <= $startsAt || $minuteOf($endsAt) > 1440) {
            throw ValidationException::withMessages(['slots' => "Khung giờ {$start->format('d/m H:i')} phải kết thúc trong cùng ngày."]);
        }

        $bounds = [$start, ...$timeline->changesBetween($startsAt, $endsAt), $endsAt];
        $parts = [];
        $exact = 0;
        foreach (array_slice($bounds, 0, -1) as $index => $from) {
            [$version, $intervals] = CourtPriceTimeline::pricing(
                $timeline->versionAt(PriceTable::KIND_NORMAL, $from),
                $timeline->versionAt(PriceTable::KIND_HOLIDAY, $from),
                $start->toDateString(),
                $start->dayOfWeekIso,
            );
            $cursor = $minuteOf($from);
            $stop = $minuteOf($bounds[$index + 1]);
            foreach ($intervals as $interval) {
                if ($interval->end_minute <= $cursor) {
                    continue;
                }
                if ($cursor >= $stop || $interval->start_minute > $cursor) {
                    break;
                }
                $partEnd = min($interval->end_minute, $stop);
                $parts[] = [
                    'price_table_id' => (int) $version?->price_table_id,
                    'price_table_version_id' => (int) $interval->price_table_version_id,
                    'starts_at' => $day->addMinutes($cursor)->utc()->toIso8601String(),
                    'ends_at' => $day->addMinutes($partEnd)->utc()->toIso8601String(),
                    'minutes' => $partEnd - $cursor,
                    'price_per_hour' => $interval->price_per_hour,
                ];
                $exact += ($partEnd - $cursor) * $interval->price_per_hour;
                $cursor = $partEnd;
            }
            if ($cursor < $stop) {
                throw ValidationException::withMessages(['slots' => "Khung giờ {$day->addMinutes($cursor)->format('d/m H:i')} chưa mở bán."]);
            }
        }
        $rental = intdiv($exact, 60);

        return new RentalQuote(rental: $rental, discount: 0, total: $rental, snapshot: ['parts' => $parts, 'voucher' => null]);
    }
}

<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\PriceRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Picks the one unit price of a slot from a court's price rules and legacy hourly prices. */
class PriceTierResolver
{
    private const PRECEDENCE = [
        'date_exception' => 4,
        'holiday' => 4,
        'peak' => 3,
        'weekly' => 2,
        'base' => 1,
    ];

    /**
     * The best-ranked candidate of the highest tier that touches the slot prices it. A slot that a
     * candidate covers only in part, unless a better-ranked candidate covers it whole, is refused: one
     * unit price cannot charge both sides of a price boundary, and a lower tier must not undercut it.
     *
     * @return array{0:int,1:array<string,mixed>}
     */
    public function priceFor(Carbon $time, Court $court, Collection $rules, Collection $legacyRules, int $duration = 60): array
    {
        $start = $time->hour * 60 + $time->minute;
        $end = $start + $duration;
        foreach (array_keys(self::PRECEDENCE) as $type) {
            $candidates = $rules
                ->filter(fn (PriceRule $rule): bool => $rule->type === $type && $this->appliesOn($rule, $time))
                ->map(fn (PriceRule $rule): array => [
                    'rank' => [$this->scopeRank($rule, $court), (int) $rule->priority, (int) $rule->version, (int) $rule->id],
                    'window' => $this->window($rule),
                    'price' => (int) $rule->price,
                    'snapshot' => [
                        'id' => $rule->id,
                        'type' => $rule->type,
                        'version' => $rule->version,
                        'priority' => $rule->priority,
                        'effective_from' => $rule->effective_from?->toDateString(),
                        'effective_until' => $rule->effective_until?->toDateString(),
                        'price' => (int) $rule->price,
                    ],
                ]);
            if ($type === 'weekly') {
                // A legacy price row is court-specific: it beats venue-wide rules, but not a court rule. It is one slot's
                // price, so, as the exact match before, it prices only slots of its own length.
                $candidates = $candidates->concat($legacyRules
                    ->filter(fn (CourtPriceSlot $slot): bool => (int) $slot->weekday === $time->dayOfWeekIso)
                    ->map(fn (CourtPriceSlot $slot): array => [
                        'rank' => [4, -1, 0, 0],
                        'window' => [
                            VenueOperatingCalendar::minuteOfDay((string) $slot->starts_at),
                            VenueOperatingCalendar::minuteOfDay((string) $slot->ends_at, true),
                        ],
                        'price' => (int) $slot->price,
                        'snapshot' => [
                            'id' => 'legacy:'.$slot->id,
                            'type' => 'weekly',
                            'version' => 1,
                            'priority' => 0,
                            'effective_from' => null,
                            'effective_until' => null,
                            'price' => (int) $slot->price,
                        ],
                    ])
                    ->filter(fn (array $candidate): bool => $candidate['window'][1] - $candidate['window'][0] === $duration));
            }

            $touching = $candidates->filter(fn (array $candidate): bool => $candidate['window'][0] < $end && $candidate['window'][1] > $start);
            if ($touching->isEmpty()) {
                continue;
            }
            [$covering, $partial] = $touching->partition(fn (array $candidate): bool => $candidate['window'][0] <= $start && $candidate['window'][1] >= $end);
            $best = $covering->sortByDesc(fn (array $candidate): array => $candidate['rank'])->first();
            if ($best === null || $partial->contains(fn (array $candidate): bool => $candidate['rank'] > $best['rank'])) {
                throw ValidationException::withMessages(['slots' => "Khung giờ {$time->format('d/m H:i')} đi qua mốc đổi giá, vui lòng chọn giờ khác."]);
            }

            return [$best['price'], $best['snapshot']];
        }
        throw ValidationException::withMessages(['slots' => "Khung giờ {$time->format('d/m H:i')} chưa mở bán."]);
    }

    private function appliesOn(PriceRule $rule, Carbon $time): bool
    {
        $date = $time->toDateString();
        if (($rule->effective_from && $date < $rule->effective_from->toDateString())
            || ($rule->effective_until && $date > $rule->effective_until->toDateString())) {
            return false;
        }
        if (in_array($rule->type, ['date_exception', 'holiday'], true)
            && $rule->exception_date?->toDateString() !== $date) {
            return false;
        }

        return $rule->type !== 'weekly' || (int) $rule->weekday === $time->dayOfWeekIso;
    }

    /**
     * The rule's daily window in minutes; a base rule, or one without both bounds, spans the whole day.
     *
     * @return array{0:int,1:int}
     */
    private function window(PriceRule $rule): array
    {
        if ($rule->type === 'base' || $rule->starts_at === null || $rule->ends_at === null) {
            return [0, 1440];
        }

        return [
            VenueOperatingCalendar::minuteOfDay((string) $rule->starts_at),
            VenueOperatingCalendar::minuteOfDay((string) $rule->ends_at, true),
        ];
    }

    private function scopeRank(PriceRule $rule, Court $court): int
    {
        if ($rule->court_id === $court->id) {
            return 4;
        }
        if ($rule->sport_type !== null && $rule->sport_type === $court->sport_type) {
            return 3;
        }
        if ($rule->venue_id === $court->venue_id) {
            return 2;
        }

        return 1;
    }
}

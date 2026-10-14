<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\PriceRule;
use App\Models\Venue;
use Illuminate\Support\Collection;

/** Loads the active price rules that can price a court; a court with none is not public or bookable. */
class CourtPriceRuleService
{
    /**
     * Two queries for any number of courts. Courts need `venue` loaded for its organization.
     *
     * @param  iterable<Court>  $courts
     * @return array<int, array{rules: Collection<int, PriceRule>, slots: Collection<int, CourtPriceSlot>}>
     */
    public function forCourts(iterable $courts): array
    {
        $courts = collect($courts);
        $rules = PriceRule::query()
            ->withoutGlobalScope('tenant_isolation')
            ->whereIn('organization_id', $courts->map(fn (Court $court): int => $this->organizationId($court))->unique()->values())
            ->where('is_active', true)
            ->get();
        $slots = CourtPriceSlot::query()->whereIn('court_id', $courts->pluck('id'))->where('is_active', true)->get()->groupBy('court_id');

        return $courts->mapWithKeys(fn (Court $court): array => [(int) $court->id => [
            'rules' => $rules->filter(fn (PriceRule $rule): bool => $this->appliesTo($rule, $court))->values(),
            'slots' => $slots->get($court->id, collect())->values(),
        ]])->all();
    }

    /** @return array{rules: Collection<int, PriceRule>, slots: Collection<int, CourtPriceSlot>} */
    public function forCourt(Court $court): array
    {
        return $this->forCourts([$court->loadMissing('venue')])[(int) $court->id];
    }

    public function isPriced(Court $court): bool
    {
        return isset($this->pricedCourtIds([$court->loadMissing('venue')])[(int) $court->id]);
    }

    /**
     * @param  iterable<Court>  $courts
     * @return array<int, bool> priced court ids as keys
     */
    public function pricedCourtIds(iterable $courts): array
    {
        return collect($this->forCourts($courts))
            ->filter(fn (array $loaded): bool => $loaded['rules']->isNotEmpty() || $loaded['slots']->isNotEmpty())
            ->map(fn (): bool => true)
            ->all();
    }

    private function appliesTo(PriceRule $rule, Court $court): bool
    {
        return (int) $rule->organization_id === $this->organizationId($court)
            && ($rule->venue_id === null || (int) $rule->venue_id === (int) $court->venue_id)
            && ($rule->sport_zone_id === null || (int) $rule->sport_zone_id === (int) $court->sport_zone_id)
            && ($rule->court_id === null || (int) $rule->court_id === (int) $court->id);
    }

    private function organizationId(Court $court): int
    {
        $venue = $court->venue;

        return $venue instanceof Venue ? (int) $venue->organization_id : 0;
    }
}

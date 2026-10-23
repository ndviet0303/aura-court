<?php

namespace Database\Seeders\Concerns;

use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\ReservationSlot;
use Carbon\Carbon;

/** One definition of free court time for every seeder that books courts, so seeded data never double-books a court or a player. */
trait FindsFreeCourtTime
{
    /**
     * First court and start hour accepted by $fits: the given court, then the venue's other courts of the same sport by code;
     * on each court the requested hour, later hours, then earlier ones, keeping the session within 06:00-23:00.
     *
     * @param  callable(Court, int): bool  $fits
     * @return array{0: Court, 1: int}
     */
    protected function firstFit(Court $court, int $hour, int $hours, callable $fits): array
    {
        $courts = Court::with(['venue'])
            ->where('venue_id', $court->venue_id)
            ->where('sport_type', $court->sport_type)
            ->whereKeyNot($court->id)
            ->orderBy('code')
            ->get()
            ->prepend($court);
        $startHours = [];
        for ($candidate = $hour; $candidate + $hours <= 23; $candidate++) {
            $startHours[] = $candidate;
        }
        for ($candidate = $hour - 1; $candidate >= 6; $candidate--) {
            $startHours[] = $candidate;
        }

        foreach ($courts as $candidateCourt) {
            foreach ($startHours as $startHour) {
                if ($fits($candidateCourt, $startHour)) {
                    return [$candidateCourt, $startHour];
                }
            }
        }

        throw new \RuntimeException("No free {$hours}h court time near {$court->code} from {$hour}:00.");
    }

    /** No active booking, reservation, stop of the court or its venue, or match on the court, nor an active reservation of the player, overlaps [from, to). */
    protected function isFree(int $courtId, Carbon $from, Carbon $to, ?int $userId = null): bool
    {
        $overlapping = fn ($query): bool => $query->where('starts_at', '<', $to->copy()->utc())->where('ends_at', '>', $from->copy()->utc())->exists();

        return ! $overlapping(BookingSlot::where('court_id', $courtId)->whereIn('status', ['held', 'confirmed']))
            && ! OperationalStop::query()->affectingCourt($courtId, (int) Court::whereKey($courtId)->value('venue_id'))->overlapping($from, $to)->exists()
            && ! $overlapping(ReservationSlot::withoutGlobalScope('tenant_isolation')
                ->whereIn('status', ['held', 'confirmed'])
                ->where(fn ($query) => $query->where('court_id', $courtId)
                    ->when($userId, fn ($query) => $query->orWhereHas('reservation', fn ($reservation) => $reservation->where('user_id', $userId)))));
    }
}

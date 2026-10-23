<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * BR-38, the single query for bookings affected by an operational change (design D7). A booking is affected while it
 * is held before its deadline, confirmed or in use, not completed or no-show, and has a slot that ends after now and
 * either overlaps the stop window (D5: a stop without a recorded end runs forever; the expected end never counts) or
 * no longer lies within the effective service time.
 *
 * Results are arrays {reservations: Collection<int, Reservation>} keyed by id.
 */
class AffectedReservationQuery
{
    public function __construct(private readonly VenueOperatingCalendar $calendar) {}

    /** A venue-level change checks every court of the venue, a court-level change only that court. */
    public function forStop(Venue|Court $object, Carbon $startsAt, ?Carbon $endedAt = null): array
    {
        return $this->overlapping($this->courtIds($object), $startsAt, $endedAt);
    }

    /** Every court of the venue, for a suspension from $from until it is lifted ($until null). */
    public function forVenue(Venue $venue, Carbon $from, ?Carbon $until = null): array
    {
        return $this->overlapping($this->courtIds($venue), $from, $until);
    }

    /** @param  list<int>  $courtIds */
    public function overlapping(array $courtIds, Carbon $from, ?Carbon $until = null): array
    {
        $now = now()->utc();
        $after = $from->greaterThan($now) ? $from->copy()->utc() : $now;
        $window = fn (Builder $query) => $query->where('ends_at', '>', $after)
            ->when($until, fn (Builder $q) => $q->where('starts_at', '<', $until->copy()->utc()));

        return $this->result($window($this->slots($courtIds))->get());
    }

    /**
     * Future bookings on these courts that are not entirely within the effective service time.
     *
     * @param  list<int>  $courtIds
     */
    public function outsideService(array $courtIds): array
    {
        $now = now()->utc();
        $closed = fn (ReservationSlot $row): bool => $row->court instanceof Court && ! $this->calendar->isOpen($row->court, $row->starts_at, $row->ends_at);
        // ponytail: one calendar lookup per future slot; preload the calendar per court and date if venues hold thousands.
        $slots = $this->slots($courtIds)->where('ends_at', '>', $now)->with('court.venue')->get()->filter($closed);

        return $this->result($slots);
    }

    /** What $after lists that $before did not: bookings that are no longer within the service time. */
    public function without(array $after, array $before): array
    {
        return ['reservations' => $after['reservations']->diffKeys($before['reservations'])];
    }

    public function isEmpty(array $affected): bool
    {
        return $affected['reservations']->isEmpty();
    }

    /** The preview and 409 body. A platform admin's suspension preview leaves out customer contacts. */
    public function payload(array $affected, bool $contacts = true): array
    {
        return [
            'bookings' => $affected['reservations']->values()->map(fn (Reservation $reservation): array => [
                'reservation_id' => $reservation->id,
                'code' => $reservation->code,
                'status' => $reservation->status,
                'in_use' => data_get($reservation->metadata, 'operational_status') === 'in_use',
                'court_id' => $reservation->court_id,
                'court_code' => $reservation->court?->code,
                'starts_at' => $reservation->starts_at?->toIso8601String(),
                'ends_at' => $reservation->ends_at?->toIso8601String(),
            ] + ($contacts ? [
                'customer_name' => $reservation->booker['name'] ?? $reservation->user?->name,
                'customer_phone' => $reservation->booker['phone'] ?? $reservation->user?->phone,
            ] : []))->all(),
        ];
    }

    /** @return list<int> */
    public function courtIds(Venue|Court $object): array
    {
        return $object instanceof Court
            ? [(int) $object->id]
            : $object->courts()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return Builder<ReservationSlot> */
    private function slots(array $courtIds): Builder
    {
        return ReservationSlot::query()->withoutGlobalScope('tenant_isolation')
            ->whereIn('court_id', $courtIds)
            ->whereIn('status', ['held', 'confirmed', 'in_use'])
            ->whereHas('reservation', fn (Builder $reservation) => $this->affectable($reservation->withoutGlobalScope('tenant_isolation')));
    }

    /** Held before its deadline, confirmed or in use; never cancelled, expired, completed or no-show. */
    private function affectable(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $status) => $status->whereIn('status', ['confirmed', 'in_use'])
                ->orWhere(fn (Builder $held) => $held->where('status', 'held')
                    ->where(fn (Builder $deadline) => $deadline->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))))
            ->where(fn (Builder $operational) => $operational->whereNull('metadata->operational_status')
                ->orWhereNotIn('metadata->operational_status', ['completed', 'no_show']));
    }

    private function result(Collection $slots): array
    {
        $reservations = Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->with([
                'user:id,name,phone',
                'court:id,code,venue_id',
                'slots' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->whereIn('status', ['held', 'confirmed', 'in_use']),
            ])
            ->whereIn('id', $slots->pluck('reservation_id')->unique()->all())
            ->orderBy('id')
            ->get();

        return ['reservations' => $reservations->keyBy('id')];
    }
}

<?php

namespace App\Services\Reservation;

use App\Models\AffectedReservationCase;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\Venue;
use App\Models\VenueScheduleException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Design D7: a change that touches bookings is previewed, saved only with an acknowledgement of a non-empty affected
 * list, and opens one pending case per affected booking and source. Callers lock in the global order of the design
 * ("Kiểm eligibility dưới lock"): courts by id, then the venue, then venue_suspensions, before computing the list.
 */
class AffectedCaseOpener
{
    public function __construct(private readonly AffectedReservationQuery $query) {}

    /**
     * Without the acknowledgement a non-empty list is a 409 carrying it; the caller's transaction rolls back.
     * A platform admin's suspension passes $contacts false: the admin never sees customer contacts.
     */
    public function assertAcknowledged(array $affected, bool $acknowledged, bool $contacts = true): void
    {
        if ($acknowledged || $this->query->isEmpty($affected)) {
            return;
        }

        abort(response()->json([
            'message' => 'Thay đổi ảnh hưởng đến các lượt đặt hiện có. Vui lòng xem danh sách và xác nhận để lưu.',
            'code' => 'AFFECTED_BOOKINGS_UNACKNOWLEDGED',
            'affected' => $this->query->payload($affected, $contacts),
        ], 409));
    }

    /**
     * One pending case per booking for this source; a booking that already has a case for it keeps that case.
     *
     * @param  Collection<int, Reservation>  $reservations
     */
    public function open(Collection $reservations, string $sourceType, int $sourceId, int $venueId, ?Carbon $windowStart = null, ?Carbon $windowEnd = null): int
    {
        $now = now();

        return AffectedReservationCase::query()->insertOrIgnore($reservations->values()->map(fn ($reservation): array => [
            'reservation_id' => $reservation->id,
            'venue_id' => $venueId,
            'court_id' => $reservation->court_id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_starts_at' => $windowStart?->copy()->utc()->format('Y-m-d H:i:s'),
            'source_ends_at' => $windowEnd?->copy()->utc()->format('Y-m-d H:i:s'),
            'status' => AffectedReservationCase::STATUS_PENDING,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    /**
     * Lock the courts by id, then the venue.
     *
     * @param  list<int>  $courtIds
     */
    public function lockScope(array $courtIds, int $venueId): Venue
    {
        Court::query()->whereIn('id', $courtIds)->orderBy('id')->lockForUpdate()->get();

        return Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Operating hours, closed days, dated exceptions and court schedules: lock, apply the change, and list the bookings
     * that were within the service time before it and are not after it. A preview rolls everything back.
     *
     * @param  callable(Venue): Model  $apply  returns the saved or deleted schedule or exception, the case source
     * @return array{source: Model, affected: array}
     */
    public function scheduleChange(int $venueId, ?int $courtId, string $sourceType, bool $preview, bool $acknowledged, callable $apply): array
    {
        $courtIds = $courtId !== null
            ? [$courtId]
            : Court::query()->where('venue_id', $venueId)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $work = function () use ($venueId, $courtIds, $sourceType, $preview, $acknowledged, $apply): array {
            $venue = $this->lockScope($courtIds, $venueId);
            $before = $this->query->outsideService($courtIds);
            $source = $apply($venue);
            $affected = $this->query->without($this->query->outsideService($courtIds), $before);
            if (! $preview) {
                $this->assertAcknowledged($affected, $acknowledged);
                [$start, $end] = $this->window($source, $venue);
                $this->open($affected['reservations'], $sourceType, (int) $source->getKey(), $venueId, $start, $end);
            }

            return ['source' => $source, 'affected' => $affected];
        };

        if (! $preview) {
            return DB::transaction($work);
        }
        DB::beginTransaction();
        try {
            return $work();
        } finally {
            DB::rollBack();
        }
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} an exception covers its date; weekly hours have no window */
    private function window(Model $source, Venue $venue): array
    {
        if (! $source instanceof VenueScheduleException) {
            return [null, null];
        }
        $start = Carbon::parse($source->exception_date->toDateString(), $venue->timezone);

        return [$start, $start->copy()->addDay()];
    }
}

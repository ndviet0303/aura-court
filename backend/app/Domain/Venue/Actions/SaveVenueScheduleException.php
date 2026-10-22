<?php

namespace App\Domain\Venue\Actions;

use App\Models\AffectedReservationCase;
use App\Models\Venue;
use App\Models\VenueScheduleException;
use App\Services\Reservation\AffectedCaseOpener;
use App\Services\Reservation\AffectedReservationQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/** Dated venue or court exceptions, created, changed or deleted under design D7 (preview, acknowledgement, cases). */
class SaveVenueScheduleException
{
    public function __construct(
        private readonly AffectedCaseOpener $cases,
        private readonly AffectedReservationQuery $affected,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function execute(int $venueId, int $actorId, array $data): VenueScheduleException
    {
        return $this->saved($this->save($venueId, $actorId, $data, false)['source']);
    }

    /** @param  array<string, mixed>  $data */
    public function preview(int $venueId, int $actorId, array $data): array
    {
        return $this->affected->payload($this->save($venueId, $actorId, $data, true)['affected']);
    }

    public function delete(int $venueId, int $exceptionId, bool $acknowledged): VenueScheduleException
    {
        return $this->saved($this->remove($venueId, $exceptionId, $acknowledged, false)['source']);
    }

    public function previewDelete(int $venueId, int $exceptionId): array
    {
        return $this->affected->payload($this->remove($venueId, $exceptionId, false, true)['affected']);
    }

    private function save(int $venueId, int $actorId, array $data, bool $preview): array
    {
        $courtId = isset($data['court_id']) ? (int) $data['court_id'] : null;

        return $this->cases->scheduleChange($venueId, $courtId, AffectedReservationCase::SOURCE_SCHEDULE_EXCEPTION, $preview, (bool) ($data['acknowledge_affected'] ?? false),
            fn (Venue $venue): VenueScheduleException => $this->exceptions($venue)->updateOrCreate([
                'venue_id' => $venue->id,
                'scope_key' => $courtId === null ? 'venue' : 'court:'.$courtId,
                'exception_date' => $data['exception_date'],
            ], [
                'court_id' => $courtId,
                'name' => $data['name'],
                'type' => $data['type'],
                'intervals' => $data['intervals'] ?? [],
                'timezone' => $data['timezone'],
                'created_by' => $actorId,
            ]));
    }

    private function remove(int $venueId, int $exceptionId, bool $acknowledged, bool $preview): array
    {
        // Read unlocked only to find the scope; the row is read again under the venue lock.
        $courtId = $this->exceptions()->where('venue_id', $venueId)->findOrFail($exceptionId)->court_id;

        return $this->cases->scheduleChange($venueId, $courtId, AffectedReservationCase::SOURCE_SCHEDULE_EXCEPTION, $preview, $acknowledged,
            function (Venue $venue) use ($exceptionId): VenueScheduleException {
                $exception = $this->exceptions($venue)->lockForUpdate()->findOrFail($exceptionId);
                $exception->delete();

                return $exception;
            });
    }

    /** @return Builder<VenueScheduleException> */
    private function exceptions(?Venue $venue = null): Builder
    {
        return VenueScheduleException::query()->when($venue, fn (Builder $q) => $q->where('venue_id', $venue->id));
    }

    private function saved(VenueScheduleException $exception): VenueScheduleException
    {
        Cache::increment('venues_version');
        Cache::increment('courts_version');

        return $exception;
    }
}

<?php

namespace App\Domain\Venue\Actions;

use App\Models\AffectedReservationCase;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use App\Services\Reservation\AffectedCaseOpener;
use App\Services\Reservation\AffectedReservationQuery;
use Illuminate\Support\Facades\Cache;

/** Weekly venue hours and court service schedules, saved under design D7 (preview, acknowledgement, cases). */
class SaveVenueOperatingSchedule
{
    public function __construct(
        private readonly AffectedCaseOpener $cases,
        private readonly AffectedReservationQuery $affected,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function execute(int $venueId, int $actorId, array $data): VenueOperatingSchedule
    {
        $schedule = $this->run($venueId, $actorId, $data, false)['source'];
        Cache::increment('venues_version');
        Cache::increment('courts_version');

        return $schedule->fresh();
    }

    /** @param  array<string, mixed>  $data */
    public function preview(int $venueId, int $actorId, array $data): array
    {
        return $this->affected->payload($this->run($venueId, $actorId, $data, true)['affected']);
    }

    private function run(int $venueId, int $actorId, array $data, bool $preview): array
    {
        $courtId = isset($data['court_id']) ? (int) $data['court_id'] : null;
        $scopeKey = $courtId === null ? 'venue' : 'court:'.$courtId;

        $source = $courtId === null ? AffectedReservationCase::SOURCE_VENUE_SCHEDULE : AffectedReservationCase::SOURCE_COURT_SCHEDULE;

        return $this->cases->scheduleChange($venueId, $courtId, $source, $preview, (bool) ($data['acknowledge_affected'] ?? false),
            function (Venue $venue) use ($actorId, $data, $courtId, $scopeKey): VenueOperatingSchedule {
                $schedule = VenueOperatingSchedule::query()
                    ->where('venue_id', $venue->id)
                    ->where('scope_key', $scopeKey)
                    ->lockForUpdate()
                    ->first();
                $schedule ??= new VenueOperatingSchedule([
                    'venue_id' => $venue->id,
                    'court_id' => $courtId,
                    'scope_key' => $scopeKey,
                    'version' => 0,
                ]);
                $schedule->fill([
                    'court_id' => $courtId,
                    'scope_key' => $scopeKey,
                    'timezone' => $data['timezone'],
                    'version' => ((int) $schedule->version) + 1,
                    'weekly_hours' => $data['weekly_hours'],
                    'created_by' => $actorId,
                ]);
                $schedule->save();

                return $schedule;
            });
    }
}

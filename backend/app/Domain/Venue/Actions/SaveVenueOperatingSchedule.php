<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\VenueOperatingSchedule;
use App\Services\Reservation\CourtCommitmentConflictService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaveVenueOperatingSchedule
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly CourtCommitmentConflictService $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $venueId, int $actorId, array $data): VenueOperatingSchedule
    {
        $courtId = isset($data['court_id']) ? (int) $data['court_id'] : null;
        $scopeKey = $courtId === null ? 'venue' : 'court:'.$courtId;

        $schedule = DB::transaction(function () use ($venueId, $actorId, $data, $courtId, $scopeKey): VenueOperatingSchedule {
            $venue = $this->venueRepo->findByIdWithLock($venueId);
            if (! $venue) {
                abort(404, 'Cơ sở thể thao không tồn tại.');
            }

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
            $this->conflicts->assertSchedulePreservesCommitments($venue);

            return $schedule->fresh();
        });

        Cache::increment('venues_version');
        Cache::increment('courts_version');

        return $schedule;
    }
}

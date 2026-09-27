<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\VenueScheduleException;
use App\Services\Reservation\CourtCommitmentConflictService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaveVenueScheduleException
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly CourtCommitmentConflictService $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $venueId, int $actorId, array $data): VenueScheduleException
    {
        $courtId = isset($data['court_id']) ? (int) $data['court_id'] : null;
        $scopeKey = $courtId === null ? 'venue' : 'court:'.$courtId;

        $exception = DB::transaction(function () use ($venueId, $actorId, $data, $courtId, $scopeKey): VenueScheduleException {
            $venue = $this->venueRepo->findByIdWithLock($venueId);
            if (! $venue) {
                abort(404, 'Cơ sở thể thao không tồn tại.');
            }

            $exception = VenueScheduleException::query()->updateOrCreate([
                'venue_id' => $venue->id,
                'scope_key' => $scopeKey,
                'exception_date' => $data['exception_date'],
            ], [
                'court_id' => $courtId,
                'name' => $data['name'],
                'type' => $data['type'],
                'intervals' => $data['intervals'] ?? [],
                'timezone' => $data['timezone'],
                'created_by' => $actorId,
            ]);
            $this->conflicts->assertSchedulePreservesCommitments($venue);

            return $exception;
        });

        Cache::increment('venues_version');
        Cache::increment('courts_version');

        return $exception;
    }
}

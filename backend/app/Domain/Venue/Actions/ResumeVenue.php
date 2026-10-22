<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Models\OperationalStop;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D4: the owner resumes a paused venue under the opening conditions of OpenVenue. The open venue pause ends now
 * with its actor; courts keep their statuses and a suspension is never lifted here.
 */
class ResumeVenue
{
    public function __construct(
        private readonly OperationalStatusRepositoryInterface $statuses,
        private readonly OpenVenue $openVenue,
    ) {}

    public function execute(int $venueId, int $actorId): Venue
    {
        return DB::transaction(function () use ($venueId, $actorId): Venue {
            $venue = $this->statuses->lockVenue($venueId) ?? abort(404, 'Cơ sở không tồn tại.');
            LifecycleGuard::assertCommandSource((string) $venue->status, Venue::STATUS_PAUSED, Venue::STATUS_ACTIVE);
            $this->openVenue->assertConditions($venue, 'Chưa đủ điều kiện để mở lại cơ sở.');

            $pause = $this->statuses->openStop((int) $venue->id, null, [OperationalStop::KIND_MAINTENANCE, OperationalStop::KIND_BUSINESS]);
            if ($pause !== null) {
                $this->statuses->endStop($pause, $actorId);
            }
            $this->statuses->setStatus($venue, Venue::STATUS_ACTIVE);
            DB::afterCommit(fn () => Cache::increment('venues_version'));

            return $venue;
        });
    }
}

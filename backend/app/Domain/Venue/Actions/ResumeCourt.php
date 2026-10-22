<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Events\CourtStatusUpdated;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D4: a paused court returns to active under the opening conditions of OpenCourt. The open court pause ends now
 * with its actor. BR-34: the owner resumes any pause; assigned staff only a maintenance pause.
 */
class ResumeCourt
{
    public function __construct(
        private readonly OperationalStatusRepositoryInterface $statuses,
        private readonly OpenCourt $openCourt,
    ) {}

    public function execute(int $courtId, User $actor): Court
    {
        return DB::transaction(function () use ($courtId, $actor): Court {
            $court = $this->statuses->lockCourt($courtId) ?? abort(404, 'Sân không tồn tại.');
            $pause = $this->statuses->openStop((int) $court->venue_id, (int) $court->id, [OperationalStop::KIND_MAINTENANCE, OperationalStop::KIND_BUSINESS]);
            if ($pause?->kind !== OperationalStop::KIND_MAINTENANCE && ! $actor->canManageVenue((int) $court->venue_id)) {
                abort(403, 'Nhân viên chỉ được khôi phục sân khỏi tạm ngừng bảo trì.');
            }
            LifecycleGuard::assertCommandSource((string) $court->status, Court::STATUS_PAUSED, Court::STATUS_ACTIVE);
            $this->openCourt->assertConditions($court, 'Chưa đủ điều kiện để mở lại sân.');

            if ($pause !== null) {
                $this->statuses->endStop($pause, (int) $actor->id);
            }
            $this->statuses->setStatus($court, Court::STATUS_ACTIVE);
            DB::afterCommit(function () use ($court, $pause): void {
                Cache::increment('courts_version');
                CourtStatusUpdated::dispatch((int) $court->venue_id, (int) $court->id, Court::STATUS_ACTIVE, $pause === null ? [] : [
                    'operational_stop_id' => $pause->id,
                    'kind' => $pause->kind,
                ]);
            });

            return $court;
        });
    }
}

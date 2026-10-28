<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateDraftVenue
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(int $venueId, int $actorId, array $attributes): Venue
    {
        $updated = DB::transaction(function () use ($venueId, $actorId, $attributes) {
            $venue = $this->venueRepo->findByIdWithLock($venueId);
            if (! $venue) {
                abort(404, 'Cơ sở thể thao không tồn tại.');
            }

            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($venue->organization_id);
            if (! $org) {
                abort(404, 'Câu lạc bộ không tồn tại.');
            }

            $hasPermission = $org->members()
                ->where('user_id', $actorId)
                ->whereIn('role', ['organization_owner'])
                ->where('status', 'active')
                ->exists();

            if (! $hasPermission) {
                abort(403, 'Bạn không có quyền chỉnh sửa cơ sở này.');
            }

            if ($venue->isClosed()) {
                abort(409, 'Cơ sở đã ngừng kinh doanh, không thể chỉnh sửa.');
            }

            // D3: status changes only through the open, pause, resume and close commands; SaveVenueRequest refuses it.
            $updated = $this->venueRepo->updateVenue($venue, $attributes);

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'venue.draft.updated',
                auditable: $updated,
                metadata: [
                    'updated_fields' => array_keys($attributes),
                ]
            );

            return $updated;
        });

        // D1: public lists read the saved values with no review step, so drop their cached copies after commit.
        Cache::increment('venues_version');

        return $updated;
    }
}

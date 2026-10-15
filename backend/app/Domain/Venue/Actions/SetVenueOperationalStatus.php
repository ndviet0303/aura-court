<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetVenueOperationalStatus
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(
        int $venueId,
        int $actorId,
        string $status,
        ?string $reason = null
    ): Venue {
        return DB::transaction(function () use ($venueId, $actorId, $status, $reason) {
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
                abort(403, 'Bạn không có quyền thay đổi trạng thái vận hành của cơ sở này.');
            }

            if (! in_array($status, ['published', 'temporarily_closed', 'closed'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Trạng thái vận hành không hợp lệ.',
                ]);
            }

            // A venue that was never published cannot be set to operational states directly
            if (! $venue->isPublished() && ! $venue->isTemporarilyClosed() && ! $venue->isClosed()) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể thay đổi trạng thái vận hành của cơ sở đã từng được xuất bản.',
                ]);
            }

            $isActive = $status === 'published';
            $payload = [
                'status' => $status,
                'is_active' => $isActive,
                'status_reason' => $reason,
            ];

            $updated = $this->venueRepo->updateVenue($venue, $payload);

            Cache::increment('venues_version');

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'venue.operational_status.changed',
                auditable: $updated,
                metadata: [
                    'previous_status' => $venue->status,
                    'new_status' => $status,
                    'reason' => $reason,
                ]
            );

            return $updated;
        });
    }
}

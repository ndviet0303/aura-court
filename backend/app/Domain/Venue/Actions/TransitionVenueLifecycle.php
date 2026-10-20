<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionVenueLifecycle
{
    public const ALLOWED_STATUSES = [
        'published',
        'temporarily_closed',
        'suspended',
        'closed',
        'archived',
    ];

    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly EvaluateVenueReadiness $readiness,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(
        int $venueId,
        int $actorId,
        string $targetStatus,
        ?string $reason = null,
        bool $isPlatformAdmin = false
    ): Venue {
        if (! in_array($targetStatus, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Trạng thái chuyển tiếp không hợp lệ.',
            ]);
        }

        return DB::transaction(function () use ($venueId, $actorId, $targetStatus, $reason, $isPlatformAdmin) {
            $venue = $this->venueRepo->findByIdWithLock($venueId);
            if (! $venue) {
                abort(404, 'Cơ sở thể thao không tồn tại.');
            }

            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($venue->organization_id);
            if (! $org) {
                abort(404, 'Câu lạc bộ không tồn tại.');
            }

            $hasOrgPermission = $org->members()
                ->where('user_id', $actorId)
                ->whereIn('role', ['organization_owner'])
                ->where('status', 'active')
                ->exists();

            if (! $isPlatformAdmin && ! $hasOrgPermission) {
                abort(403, 'Bạn không có quyền quản trị cơ sở thể thao này.');
            }

            // Platform compliance suspension requires platform admin
            if ($targetStatus === 'suspended' && ! $isPlatformAdmin) {
                abort(403, 'Chỉ quản trị viên nền tảng mới có quyền tạm ngưng hoạt động cơ sở thể thao.');
            }

            // Any move out of a platform suspension lifts it, so only the platform admin may make one
            if ($venue->isSuspended() && ! $isPlatformAdmin) {
                abort(403, 'Cơ sở đang bị tạm ngưng do tuân thủ, chỉ quản trị viên nền tảng mới có thể khôi phục.');
            }

            // Closure is final for every actor: a closed venue may only be archived
            if (($venue->isClosed() && $targetStatus !== 'archived') || $venue->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'Cơ sở đã ngừng kinh doanh, không thể hoạt động hoặc tạm ngừng trở lại.',
                ]);
            }

            // An unreviewed venue cannot enter the pause/resume pair either, or it would reach published in two steps
            $isUnreviewed = $venue->isDraft() || $venue->isPendingPublication() || $venue->isRevisionRequired();
            if ($isUnreviewed && in_array($targetStatus, ['published', 'temporarily_closed'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Cơ sở thể thao chưa qua xét duyệt xuất bản, không thể chuyển sang hoạt động hoặc tạm ngừng.',
                ]);
            }

            if ($targetStatus === 'published') {
                // Real-time readiness & entitlement re-check
                $eval = $this->readiness->execute($venue);
                if (! $eval['is_ready']) {
                    throw ValidationException::withMessages([
                        'readiness' => 'Không thể khôi phục hoạt động do cơ sở chưa đáp ứng điều kiện vận hành.',
                        'missing_items' => $eval['missing_items'],
                    ]);
                }
            }

            $previousStatus = $venue->status;
            $isActive = $targetStatus === 'published';

            $payload = [
                'status' => $targetStatus,
                'is_active' => $isActive,
                'status_reason' => $targetStatus === 'published' ? null : $reason,
            ];

            $updated = $this->venueRepo->updateVenue($venue, $payload);

            Cache::increment('venues_version');

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'venue.lifecycle.transitioned',
                auditable: $updated,
                metadata: [
                    'previous_status' => $previousStatus,
                    'new_status' => $targetStatus,
                    'reason' => $reason,
                    'is_platform_admin' => $isPlatformAdmin,
                ]
            );

            return $updated;
        });
    }
}

<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformSubscription;
use Illuminate\Support\Facades\DB;

class CancelSubscriptionRenewal
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(int $organizationId, int $actorId, ?string $reason = null): PlatformSubscription
    {
        return DB::transaction(function () use ($organizationId, $actorId, $reason) {
            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($organizationId);
            if (! $org) {
                abort(404, 'Câu lạc bộ / tổ chức không tồn tại.');
            }

            $isOwner = $org->members()
                ->where('user_id', $actorId)
                ->where('role', 'organization_owner')
                ->where('status', 'active')
                ->exists();

            if (! $isOwner) {
                abort(403, 'Chỉ chủ sở hữu câu lạc bộ mới có quyền hủy gia hạn gói dịch vụ.');
            }

            $sub = $this->subRepo->findActiveForOrganizationWithLock($organizationId);
            if (! $sub) {
                abort(404, 'Câu lạc bộ không có gói dịch vụ nào đang hoạt động.');
            }

            $updated = $this->subRepo->update($sub, [
                'auto_renew' => false,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'subscription.renew.cancelled',
                auditable: $updated,
                metadata: [
                    'reason' => $reason,
                    'ends_at' => $updated->ends_at?->toIso8601String(),
                ]
            );

            return $updated;
        });
    }
}

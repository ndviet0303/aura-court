<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SuspendPartner
{
    public function __construct(
        private readonly OrganizationLockRepositoryInterface $locks,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(int $organizationId, int $actorId, string $reason): Organization
    {
        return DB::transaction(function () use ($organizationId, $actorId, $reason) {
            $org = $this->locks->lockOrganizationForUpdate($organizationId);
            if (! $org) {
                abort(404, 'Đối tác không tồn tại.');
            }

            if (! $org->isApprovedPartner()) {
                abort(409, 'Chỉ có thể tạm ngừng đối tác đang ở trạng thái hoạt động.');
            }

            $updated = $this->locks->updatePartnerStatus($org, 'suspended', $reason);

            Cache::increment('venues_version');

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'organization.partner.suspended',
                auditable: $updated,
                metadata: [
                    'reason' => $reason,
                    'suspended_at' => now()->toIso8601String(),
                ]
            );

            return $updated;
        });
    }
}

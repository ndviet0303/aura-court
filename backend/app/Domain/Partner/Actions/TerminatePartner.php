<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TerminatePartner
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

            if ($org->isTerminatedPartner()) {
                abort(409, 'Đối tác đã bị chấm dứt hợp tác trước đó.');
            }

            $updated = $this->locks->updatePartnerStatus($org, 'terminated', $reason);

            Cache::increment('venues_version');

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'organization.partner.terminated',
                auditable: $updated,
                metadata: [
                    'reason' => $reason,
                    'terminated_at' => now()->toIso8601String(),
                ]
            );

            return $updated;
        });
    }
}

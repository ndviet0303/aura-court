<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RestorePartner
{
    public function __construct(
        private readonly OrganizationLockRepositoryInterface $locks,
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    /**
     * @return array{
     *     organization: Organization,
     *     has_valid_entitlement: bool,
     *     new_bookings_enabled: bool
     * }
     */
    public function execute(int $organizationId, int $actorId, ?string $notes = null): array
    {
        return DB::transaction(function () use ($organizationId, $actorId, $notes) {
            $org = $this->locks->lockOrganizationForUpdate($organizationId);
            if (! $org) {
                abort(404, 'Đối tác không tồn tại.');
            }

            if ($org->isTerminatedPartner()) {
                abort(409, 'Đối tác đã bị chấm dứt hợp tác vĩnh viễn, không thể khôi phục.');
            }

            if (! $org->isSuspendedPartner()) {
                abort(409, 'Chỉ có thể khôi phục đối tác đang ở trạng thái tạm ngừng.');
            }

            $updated = $this->locks->updatePartnerStatus($org, 'approved', null);
            Cache::increment('venues_version');

            // Fresh check on subscription entitlement
            $activeSub = $this->subRepo->findActiveForOrganization($org->id);
            $hasValidEntitlement = $activeSub !== null && $activeSub->isValid();

            Cache::increment('venues_version');

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'organization.partner.restored',
                auditable: $updated,
                metadata: [
                    'notes' => $notes,
                    'has_valid_entitlement' => $hasValidEntitlement,
                    'restored_at' => now()->toIso8601String(),
                ]
            );

            return [
                'organization' => $updated,
                'has_valid_entitlement' => $hasValidEntitlement,
                'new_bookings_enabled' => $hasValidEntitlement,
            ];
        });
    }
}

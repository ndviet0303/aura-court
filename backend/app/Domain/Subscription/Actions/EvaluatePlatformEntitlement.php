<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EvaluatePlatformEntitlement
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    /**
     * @return array{
     *     is_valid: bool,
     *     status: string,
     *     can_take_bookings: bool,
     *     subscription: ?PlatformSubscription,
     *     reason: ?string
     * }
     */
    public function execute(int $organizationId): array
    {
        return DB::transaction(function () use ($organizationId) {
            $sub = $this->subRepo->findActiveForOrganizationWithLock($organizationId);

            if (! $sub) {
                return [
                    'is_valid' => false,
                    'status' => 'no_subscription',
                    'can_take_bookings' => false,
                    'subscription' => null,
                    'reason' => 'NO_ACTIVE_SUBSCRIPTION',
                ];
            }

            // Real-time expiry detection: if recorded ends_at is in the past
            if ($sub->ends_at && $sub->ends_at->isPast()) {
                $expiredSub = $this->subRepo->update($sub, [
                    'status' => 'expired',
                ]);

                Cache::increment('venues_version');

                // Record audit log
                $this->notif->recordAudit(
                    userId: null,
                    action: 'subscription.expired',
                    auditable: $expiredSub,
                    metadata: [
                        'detected_by' => 'request_time',
                        'ends_at' => $expiredSub->ends_at?->toIso8601String(),
                        'expired_at' => now()->toIso8601String(),
                    ]
                );

                // Enqueue owner notification intent
                $ownerMember = $expiredSub->organization?->members()
                    ->where('role', 'organization_owner')
                    ->first();

                if ($ownerMember) {
                    $this->notif->recordOutboxNotification(
                        key: "subscription.expired.{$expiredSub->id}",
                        userId: (int) $ownerMember->user_id,
                        eventType: 'platform.subscription.expired',
                        payload: [
                            'title' => 'Gói dịch vụ nền tảng đã hết hạn',
                            'body' => 'Gói dịch vụ của câu lạc bộ đã hết hạn. Các lượt đặt sân mới đã tạm dừng cho đến khi gia hạn.',
                            'organization_id' => $expiredSub->organization_id,
                            'subscription_id' => $expiredSub->id,
                        ],
                        orgId: $expiredSub->organization_id
                    );
                }

                return [
                    'is_valid' => false,
                    'status' => 'expired',
                    'can_take_bookings' => false,
                    'subscription' => $expiredSub,
                    'reason' => 'SUBSCRIPTION_EXPIRED',
                ];
            }

            return [
                'is_valid' => true,
                'status' => $sub->status,
                'can_take_bookings' => true,
                'subscription' => $sub,
                'reason' => null,
            ];
        });
    }
}

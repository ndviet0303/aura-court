<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessExpiredSubscriptions
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(): int
    {
        $expiredCount = 0;

        $expiredSubscriptions = $this->subRepo->getExpiredActiveSubscriptions(now());

        foreach ($expiredSubscriptions as $sub) {
            DB::transaction(function () use ($sub, &$expiredCount) {
                $sub = $this->subRepo->findByIdWithLock((int) $sub->id);
                if (! $sub || ! in_array($sub->status, ['active', 'trial'], true)) {
                    return; // A concurrent renewal or cancellation transitioned it before the sweep locked the row.
                }
                $sub->update(['status' => 'expired']);
                $expiredCount++;

                $this->notif->recordAudit(
                    userId: null,
                    action: 'subscription.expired',
                    auditable: $sub,
                    metadata: [
                        'detected_by' => 'batch_scheduler',
                        'ends_at' => $sub->ends_at?->toIso8601String(),
                        'processed_at' => now()->toIso8601String(),
                    ]
                );

                $ownerMember = $sub->organization?->members()
                    ->where('role', 'organization_owner')
                    ->first();

                if ($ownerMember) {
                    $this->notif->recordOutboxNotification(
                        key: "subscription.expired.{$sub->id}",
                        userId: (int) $ownerMember->user_id,
                        eventType: 'platform.subscription.expired',
                        payload: [
                            'title' => 'Gói dịch vụ nền tảng đã hết hạn',
                            'body' => 'Gói dịch vụ của câu lạc bộ đã hết hạn. Vui lòng gia hạn để tiếp tục nhận đặt sân mới.',
                            'organization_id' => $sub->organization_id,
                            'subscription_id' => $sub->id,
                        ],
                        orgId: $sub->organization_id
                    );
                }
            });
        }

        if ($expiredCount > 0) {
            Cache::increment('venues_version');
        }

        return $expiredCount;
    }
}

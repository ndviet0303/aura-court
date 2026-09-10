<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformPaymentAttempt;
use Illuminate\Support\Facades\DB;

class FailSubscriptionPayment
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(string $referenceCode, string $reason, ?int $actorId = null): PlatformPaymentAttempt
    {
        return DB::transaction(function () use ($referenceCode, $reason, $actorId) {
            $attempt = $this->subRepo->findPaymentAttemptByReferenceWithLock($referenceCode);
            if (! $attempt) {
                abort(404, 'Yêu cầu thanh toán không tồn tại.');
            }

            if ($attempt->isSucceeded()) {
                abort(409, 'Giao dịch đã được ghi nhận thành công trước đó.');
            }

            $updated = $this->subRepo->updatePaymentAttempt($attempt, [
                'status' => 'failed',
                'failure_reason' => $reason,
            ]);

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'subscription.payment.failed',
                auditable: $updated,
                metadata: [
                    'reference_code' => $referenceCode,
                    'reason' => $reason,
                ]
            );

            return $updated;
        });
    }
}

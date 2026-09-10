<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Infrastructure\SubscriptionPaymentAdapterInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformPaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RenewPlatformSubscription
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly PlatformPlanRepositoryInterface $planRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly SubscriptionPaymentAdapterInterface $paymentAdapter,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    /**
     * @return array{
     *     attempt: PlatformPaymentAttempt,
     *     checkout_url: string,
     *     qr_code: ?string
     * }
     */
    public function execute(
        int $organizationId,
        int $actorId,
        ?int $planId = null,
        ?string $returnUrl = null,
        ?string $cancelUrl = null
    ): array {
        return DB::transaction(function () use ($organizationId, $actorId, $planId, $returnUrl, $cancelUrl) {
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
                abort(403, 'Chỉ chủ sở hữu câu lạc bộ mới có quyền gia hạn gói dịch vụ.');
            }

            $sub = $this->subRepo->findActiveForOrganizationWithLock($organizationId);
            if (! $sub) {
                abort(404, 'Câu lạc bộ không có gói dịch vụ nào đang hoạt động.');
            }

            $targetPlanId = $planId ?? $sub->plan_id;
            $plan = $this->planRepo->findById($targetPlanId);
            if (! $plan || ! $plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' => 'Gói dịch vụ không tồn tại hoặc đã ngừng cung cấp.',
                ]);
            }

            $referenceCode = 'SUB-REN-'.date('Ymd').'-'.strtoupper(Str::random(8));

            $attempt = $this->subRepo->createPaymentAttempt([
                'subscription_id' => $sub->id,
                'organization_id' => $org->id,
                'reference_code' => $referenceCode,
                'amount' => (int) $plan->price,
                'currency' => $plan->currency ?? 'VND',
                'provider' => 'payos',
                'status' => 'pending',
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_version' => (int) $plan->version,
                    'billing_cycle' => $plan->billing_cycle,
                    'accepted_terms_version' => (string) $plan->terms_version,
                    'actor_id' => $actorId,
                    'is_renewal' => true,
                ],
            ]);

            $gatewayRes = $this->paymentAdapter->initiatePayment($attempt, $returnUrl, $cancelUrl);

            $updatedAttempt = $this->subRepo->updatePaymentAttempt($attempt, [
                'provider' => $gatewayRes['provider'] ?? 'payos',
                'provider_ref' => $gatewayRes['provider_ref'],
            ]);

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'subscription.renew.initiated',
                auditable: $updatedAttempt,
                metadata: [
                    'subscription_id' => $sub->id,
                    'reference_code' => $referenceCode,
                    'amount' => $updatedAttempt->amount,
                ]
            );

            return [
                'attempt' => $updatedAttempt,
                'checkout_url' => $gatewayRes['checkout_url'],
                'qr_code' => $gatewayRes['qr_code'] ?? null,
            ];
        });
    }
}

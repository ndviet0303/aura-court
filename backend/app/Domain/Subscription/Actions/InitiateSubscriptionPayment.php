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

class InitiateSubscriptionPayment
{
    public function __construct(
        private readonly PlatformPlanRepositoryInterface $planRepo,
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
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
        int $planId,
        int $actorId,
        string $acceptedTermsVersion,
        ?string $returnUrl = null,
        ?string $cancelUrl = null
    ): array {
        return DB::transaction(function () use (
            $organizationId,
            $planId,
            $actorId,
            $acceptedTermsVersion,
            $returnUrl,
            $cancelUrl
        ) {
            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($organizationId);
            if (! $org) {
                abort(404, 'Câu lạc bộ / tổ chức không tồn tại.');
            }

            // Verify actor is organization_owner
            $isOwner = $org->members()
                ->where('user_id', $actorId)
                ->where('role', 'organization_owner')
                ->where('status', 'active')
                ->exists();

            if (! $isOwner) {
                abort(403, 'Chỉ chủ sở hữu câu lạc bộ mới có quyền khởi tạo thanh toán gói dịch vụ.');
            }

            $plan = $this->planRepo->findById($planId);
            if (! $plan || ! $plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' => 'Gói dịch vụ không tồn tại hoặc đã ngừng cung cấp.',
                ]);
            }

            if ($plan->price === null || $plan->price <= 0) {
                throw ValidationException::withMessages([
                    'plan' => 'Gói dịch vụ chưa được cấu hình giá thanh toán hợp lệ.',
                ]);
            }

            if ($acceptedTermsVersion !== (string) $plan->terms_version) {
                throw ValidationException::withMessages([
                    'accepted_terms_version' => 'Phiên bản điều khoản đã thay đổi. Vui lòng xác nhận điều khoản phiên bản '.$plan->terms_version.'.',
                ]);
            }

            $referenceCode = 'SUB-PAY-'.date('Ymd').'-'.strtoupper(Str::random(8));

            // Create immutable pending payment attempt
            $attempt = $this->subRepo->createPaymentAttempt([
                'subscription_id' => null, // Will be linked or created upon successful verification
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
                    'accepted_terms_version' => $acceptedTermsVersion,
                    'actor_id' => $actorId,
                    'organization_name' => $org->name,
                ],
            ]);

            // Call provider-neutral adapter
            $gatewayRes = $this->paymentAdapter->initiatePayment($attempt, $returnUrl, $cancelUrl);

            $updatedAttempt = $this->subRepo->updatePaymentAttempt($attempt, [
                'provider' => $gatewayRes['provider'] ?? 'payos',
                'provider_ref' => $gatewayRes['provider_ref'],
            ]);

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'subscription.payment.initiated',
                auditable: $updatedAttempt,
                metadata: [
                    'reference_code' => $referenceCode,
                    'amount' => $updatedAttempt->amount,
                    'provider' => $updatedAttempt->provider,
                    'provider_ref' => $updatedAttempt->provider_ref,
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

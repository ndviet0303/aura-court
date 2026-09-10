<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConfirmSubscriptionPayment
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly PlatformPlanRepositoryInterface $planRepo,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    /**
     * @return array{
     *     subscription: PlatformSubscription,
     *     attempt: PlatformPaymentAttempt,
     *     is_duplicate: bool
     * }
     */
    public function execute(
        string $referenceCode,
        string $providerTransactionId,
        int $amount,
        string $currency,
        array $rawPayload = [],
        ?int $actorId = null
    ): array {
        $attempt = $this->subRepo->findPaymentAttemptByReferenceWithLock($referenceCode);
        if (! $attempt) {
            abort(404, 'Yêu cầu thanh toán không tồn tại.');
        }

        // 1. Idempotency Check: if already succeeded, acknowledge without extending term or duplicating records
        if ($attempt->isSucceeded()) {
            $sub = $attempt->subscription ?? $this->subRepo->findActiveForOrganization($attempt->organization_id);

            return [
                'subscription' => $sub,
                'attempt' => $attempt,
                'is_duplicate' => true,
            ];
        }

        if ($attempt->isFailed()) {
            abort(409, 'Giao dịch thanh toán này đã bị đánh dấu thất bại trước đó.');
        }

        // 2. Reference, amount, and currency mismatch validation
        $amountMatches = (int) $amount === (int) $attempt->amount;
        $currencyMatches = strtoupper(trim($currency)) === strtoupper(trim($attempt->currency));

        if (! $amountMatches || ! $currencyMatches) {
            $this->subRepo->updatePaymentAttempt($attempt, [
                'status' => 'failed',
                'failure_reason' => 'AMOUNT_OR_CURRENCY_MISMATCH',
            ]);

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'subscription.payment.mismatch',
                auditable: $attempt,
                metadata: [
                    'reference_code' => $referenceCode,
                    'expected_amount' => $attempt->amount,
                    'received_amount' => $amount,
                    'expected_currency' => $attempt->currency,
                    'received_currency' => $currency,
                ]
            );

            abort(422, 'Dữ liệu giao dịch không khớp với số tiền hoặc loại tiền tệ của yêu cầu thanh toán.');
        }

        return DB::transaction(function () use (
            $attempt,
            $referenceCode,
            $providerTransactionId,
            $amount,
            $currency,
            $rawPayload,
            $actorId
        ) {
            // 3. Record immutable provider receipt
            $this->subRepo->recordReceipt([
                'payment_attempt_id' => $attempt->id,
                'provider' => $attempt->provider,
                'provider_transaction_id' => $providerTransactionId,
                'amount' => $amount,
                'currency' => $currency,
                'raw_payload' => $rawPayload,
                'verified_at' => now(),
            ]);

            // 4. Provision / Activate PlatformSubscription
            $planId = (int) $attempt->metadata['plan_id'];
            $plan = $this->planRepo->findById($planId);
            $planVersion = (int) ($attempt->metadata['plan_version'] ?? $plan?->version ?? 1);
            $billingCycle = (string) ($attempt->metadata['billing_cycle'] ?? 'monthly');
            $termsVersion = (string) ($attempt->metadata['accepted_terms_version'] ?? 'default');
            $durationDays = $billingCycle === 'yearly' ? 365 : 30;

            $existingSub = $this->subRepo->findActiveForOrganizationWithLock($attempt->organization_id);
            if ($existingSub && $existingSub->isValid()) {
                // If existing subscription is active (or trial), extend period
                $baseDate = ($existingSub->ends_at && $existingSub->ends_at->isFuture())
                    ? $existingSub->ends_at->copy()
                    : now();
                $startsAt = $existingSub->starts_at ?? now();
                $endsAt = $baseDate->addDays($durationDays);

                $subscription = $this->subRepo->update($existingSub, [
                    'plan_id' => $planId,
                    'plan_version' => $planVersion,
                    'status' => 'active',
                    'billing_cycle' => $billingCycle,
                    'price' => $attempt->amount,
                    'currency' => $attempt->currency,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'accepted_terms_version' => $termsVersion,
                    'terms_accepted_at' => now(),
                    'auto_renew' => true,
                ]);
            } else {
                $startsAt = now();
                $endsAt = now()->addDays($durationDays);

                $subscription = $this->subRepo->create([
                    'organization_id' => $attempt->organization_id,
                    'plan_id' => $planId,
                    'plan_version' => $planVersion,
                    'status' => 'active',
                    'billing_cycle' => $billingCycle,
                    'price' => $attempt->amount,
                    'currency' => $attempt->currency,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'accepted_terms_version' => $termsVersion,
                    'terms_accepted_at' => now(),
                    'auto_renew' => true,
                ]);
            }

            // 5. Update attempt status to succeeded
            $updatedAttempt = $this->subRepo->updatePaymentAttempt($attempt, [
                'subscription_id' => $subscription->id,
                'status' => 'succeeded',
                'paid_at' => now(),
            ]);

            Cache::increment('venues_version');

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'subscription.payment.succeeded',
                auditable: $subscription,
                metadata: [
                    'reference_code' => $referenceCode,
                    'provider_transaction_id' => $providerTransactionId,
                    'amount' => $amount,
                    'starts_at' => $startsAt->toIso8601String(),
                    'ends_at' => $endsAt->toIso8601String(),
                ]
            );

            return [
                'subscription' => $subscription,
                'attempt' => $updatedAttempt,
                'is_duplicate' => false,
            ];
        });
    }
}

<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConfirmSubscriptionPayment
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly PlatformPlanRepositoryInterface $planRepo,
        private readonly DurableNotificationServiceInterface $notif,
        private readonly FinancialPostingService $financials,
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
        return DB::transaction(function () use ($referenceCode, $providerTransactionId, $amount, $currency, $actorId): array {
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

            $receipt = PlatformProviderReceipt::query()
                ->where('payment_attempt_id', $attempt->id)
                ->lockForUpdate()
                ->first();
            abort_unless(
                $receipt !== null
                    && $receipt->verified_at !== null
                    && $receipt->provider === $attempt->provider
                    && $receipt->provider_transaction_id === $providerTransactionId,
                409,
                'Thanh toán vẫn đang chờ bằng chứng giao dịch đã được xác minh.'
            );

            // 2. Reference, amount, and currency mismatch validation
            $amountMatches = (int) $amount === (int) $attempt->amount
                && (int) $receipt->amount === (int) $attempt->amount;
            $currencyMatches = strtoupper(trim($currency)) === strtoupper(trim($attempt->currency))
                && strtoupper(trim($receipt->currency)) === strtoupper(trim($attempt->currency));

            if (! $amountMatches || ! $currencyMatches) {
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

            // The receipt was persisted only by a signature-verified platform-provider event.
            // 3. Provision / Activate PlatformSubscription
            $planId = (int) $attempt->metadata['plan_id'];
            $plan = $this->planRepo->findById($planId);
            $planVersion = (int) ($attempt->metadata['plan_version'] ?? $plan?->version ?? 1);
            $billingCycle = (string) ($attempt->metadata['billing_cycle'] ?? 'monthly');
            $billingMonths = (int) ($attempt->metadata['billing_months'] ?? PlatformPlan::monthsForBillingCycle($billingCycle));
            $termsVersion = (string) ($attempt->metadata['accepted_terms_version'] ?? 'default');
            if ($billingMonths < 1) {
                abort(422, 'Chu kỳ thanh toán không hợp lệ; không thể kích hoạt thuê bao.');
            }

            $org = $attempt->organization;
            if ($org && $plan) {
                $currentVenues = $org->venues()->count();
                $maxVenues = (int) ($plan->max_venues ?? 1);
                if ($currentVenues > $maxVenues) {
                    abort(422, "Không thể chuyển sang gói {$plan->name}. Số lượng cơ sở hiện tại ({$currentVenues}) vượt quá giới hạn tối đa ({$maxVenues}) của gói này.");
                }

                $currentCourts = $org->courts()->count();
                $maxCourts = (int) ($plan->max_courts ?? 4);
                if ($currentCourts > $maxCourts) {
                    abort(422, "Không thể chuyển sang gói {$plan->name}. Số lượng sân hiện tại ({$currentCourts}) vượt quá giới hạn tối đa ({$maxCourts}) của gói này.");
                }

                $currentStaff = $org->members()
                    ->whereIn('role', ['staff'])
                    ->whereIn('status', ['active', 'invited'])
                    ->count();
                $maxStaff = (int) ($plan->max_staff ?? 2);
                if ($currentStaff > $maxStaff) {
                    abort(422, "Không thể chuyển sang gói {$plan->name}. Số lượng nhân sự hiện tại ({$currentStaff}) vượt quá giới hạn tối đa ({$maxStaff}) của gói này.");
                }
            }

            $existingSub = $this->subRepo->findActiveForOrganizationWithLock($attempt->organization_id);
            if ($existingSub && $existingSub->isValid()) {
                // If existing subscription is active (or trial), extend period
                $baseDate = ($existingSub->ends_at && $existingSub->ends_at->isFuture())
                    ? $existingSub->ends_at->copy()
                    : now();
                $startsAt = $existingSub->status === 'trial'
                    ? $baseDate->copy()
                    : ($existingSub->starts_at ?? $baseDate->copy());
                $anchorDay = $existingSub->status === 'trial'
                    ? $baseDate->day
                    : ($existingSub->starts_at?->day ?? $baseDate->day);
                $endsAt = $baseDate->copy()->addMonthsNoOverflow($billingMonths, $anchorDay);

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
                $endsAt = $startsAt->copy()->addMonthsNoOverflow($billingMonths, $startsAt->day);

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

            $this->financials->postPlatformSubscriptionReceipt($receipt->fresh());

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

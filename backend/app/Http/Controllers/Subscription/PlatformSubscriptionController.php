<?php

namespace App\Http\Controllers\Subscription;

use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Domain\Subscription\Actions\ActivatePlatformTrial;
use App\Domain\Subscription\Actions\CancelSubscriptionRenewal;
use App\Domain\Subscription\Actions\InitiateSubscriptionPayment;
use App\Domain\Subscription\Actions\RenewPlatformSubscription;
use App\Domain\Subscription\Actions\SelectPlatformPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\ActivatePlatformTrialRequest;
use App\Http\Requests\Subscription\CancelSubscriptionRenewalRequest;
use App\Http\Requests\Subscription\ConfirmSubscriptionPaymentRequest;
use App\Http\Requests\Subscription\InitiateSubscriptionPaymentRequest;
use App\Http\Requests\Subscription\SelectPlatformPlanRequest;
use App\Http\Resources\Platform\PlatformSubscriptionResource;
use App\Models\PlatformPaymentAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSubscriptionController extends Controller
{
    public function current(
        Request $request,
        int $organizationId,
        PlatformSubscriptionRepositoryInterface $subRepo
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user->isOrganizationOwner($organizationId), 403, 'Chỉ chủ sở hữu câu lạc bộ mới có quyền xem thuê bao.');

        $organization = $user->organizations()->whereKey($organizationId)->first();
        abort_unless($organization, 404, 'Câu lạc bộ / tổ chức không tồn tại.');

        $sub = $subRepo->findActiveForOrganization($organizationId);
        $hasUsedTrial = $subRepo->hasUsedTrial($organizationId);
        $plan = $sub?->plan;
        $paymentAttempt = $subRepo->findLatestPaymentAttemptForOrganization($organizationId);
        $canTakeBookings = $sub !== null && $sub->isValid();

        return response()->json([
            'data' => $sub ? PlatformSubscriptionResource::make($sub) : null,
            'has_active_subscription' => $canTakeBookings,
            'has_used_trial' => $hasUsedTrial,
            'trial_eligible' => ! $hasUsedTrial && ! $canTakeBookings,
            'can_take_bookings' => $canTakeBookings,
            'payment_attempt' => $paymentAttempt ? $this->paymentAttemptData($paymentAttempt) : null,
            'quotas' => [
                'venues_count' => $organization->venues()->count(),
                'max_venues' => (int) ($plan?->max_venues ?? 0),
                'courts_count' => $organization->courts()->count(),
                'max_courts' => (int) ($plan?->max_courts ?? 0),
                'staff_count' => $organization->members()->whereIn('role', ['staff'])->where('status', 'active')->count(),
                'max_staff' => (int) ($plan?->max_staff ?? 0),
            ],
        ]);
    }

    public function selectPlan(
        SelectPlatformPlanRequest $request,
        int $organizationId,
        SelectPlatformPlan $action
    ): JsonResponse {
        $snapshot = $action->execute(
            organizationId: $organizationId,
            planId: (int) $request->validated('plan_id'),
            actorId: (int) $request->user()->id,
            acceptedTermsVersion: (string) $request->validated('accepted_terms_version')
        );

        return response()->json([
            'data' => [
                'organization_id' => $snapshot['organization_id'],
                'plan_id' => $snapshot['plan_id'],
                'plan_name' => $snapshot['plan']->name,
                'plan_version' => $snapshot['plan_version'],
                'price' => $snapshot['price'],
                'currency' => $snapshot['currency'],
                'billing_cycle' => $snapshot['billing_cycle'],
                'billing_months' => $snapshot['billing_months'],
                'accepted_terms_version' => $snapshot['accepted_terms_version'],
                'trial_available' => $snapshot['trial_available'],
                'trial_days' => $snapshot['trial_days'],
            ],
            'message' => 'Đã xác nhận lựa chọn gói dịch vụ. Vui lòng tiến hành kích hoạt dùng thử hoặc thanh toán.',
        ]);
    }

    public function activateTrial(
        ActivatePlatformTrialRequest $request,
        int $organizationId,
        ActivatePlatformTrial $action
    ): JsonResponse {
        $subscription = $action->execute(
            organizationId: $organizationId,
            planId: (int) $request->validated('plan_id'),
            actorId: (int) $request->user()->id,
            acceptedTermsVersion: (string) $request->validated('accepted_terms_version')
        );

        return response()->json([
            'data' => PlatformSubscriptionResource::make($subscription),
            'message' => 'Kích hoạt chương trình dùng thử thành công. Bạn có thể thiết lập sân bãi và quản lý câu lạc bộ.',
        ], 201);
    }

    public function initiatePayment(
        InitiateSubscriptionPaymentRequest $request,
        int $organizationId,
        InitiateSubscriptionPayment $action
    ): JsonResponse {
        $result = $action->execute(
            organizationId: $organizationId,
            planId: (int) $request->validated('plan_id'),
            actorId: (int) $request->user()->id,
            acceptedTermsVersion: (string) $request->validated('accepted_terms_version'),
            returnUrl: $request->input('return_url'),
            cancelUrl: $request->input('cancel_url')
        );

        return response()->json([
            'data' => [
                'id' => $result['attempt']->id,
                'organization_id' => $result['attempt']->organization_id,
                'plan_id' => (int) ($result['attempt']->metadata['plan_id'] ?? 0),
                'plan_version' => (int) ($result['attempt']->metadata['plan_version'] ?? 1),
                'reference_code' => $result['attempt']->reference_code,
                'amount' => $result['attempt']->amount,
                'currency' => $result['attempt']->currency,
                'status' => $result['attempt']->status,
                'provider' => $result['attempt']->provider,
                'checkout_url' => $result['checkout_url'],
                'qr_code' => $result['qr_code'],
                'created_at' => $result['attempt']->created_at?->toIso8601String(),
            ],
            'message' => 'Đã tạo hướng dẫn thanh toán. Trạng thái sẽ cập nhật sau khi máy chủ xác minh giao dịch.',
        ], 201);
    }

    public function confirmPayment(
        ConfirmSubscriptionPaymentRequest $request,
    ): JsonResponse {
        $attempt = PlatformPaymentAttempt::query()
            ->where('reference_code', (string) $request->validated('reference_code'))
            ->with('subscription')
            ->firstOrFail();
        abort_unless($request->user()?->isOrganizationOwner((int) $attempt->organization_id), 403);

        return response()->json([
            'data' => [
                'subscription' => $attempt->subscription ? PlatformSubscriptionResource::make($attempt->subscription) : null,
                'reference_code' => $attempt->reference_code,
                'status' => $attempt->status,
                'is_duplicate' => $attempt->isSucceeded(),
            ],
            'message' => $attempt->isSucceeded()
                ? 'Giao dịch đã được máy chủ xác minh trước đó.'
                : 'Thanh toán vẫn đang chờ bằng chứng đã được máy chủ xác minh.',
        ]);
    }

    public function cancelRenewal(
        CancelSubscriptionRenewalRequest $request,
        int $organizationId,
        CancelSubscriptionRenewal $action
    ): JsonResponse {
        $subscription = $action->execute(
            organizationId: $organizationId,
            actorId: (int) $request->user()->id,
            reason: $request->input('reason')
        );

        return response()->json([
            'data' => PlatformSubscriptionResource::make($subscription),
            'message' => 'Đã hủy tự động gia hạn gói dịch vụ. Quyền lợi dịch vụ vẫn duy trì đến hết chu kỳ hiện tại.',
        ]);
    }

    public function renew(
        Request $request,
        int $organizationId,
        RenewPlatformSubscription $action
    ): JsonResponse {
        $result = $action->execute(
            organizationId: $organizationId,
            actorId: (int) $request->user()->id,
            planId: $request->filled('plan_id') ? (int) $request->input('plan_id') : null,
            returnUrl: $request->input('return_url'),
            cancelUrl: $request->input('cancel_url')
        );

        return response()->json([
            'data' => [
                'id' => $result['attempt']->id,
                'organization_id' => $result['attempt']->organization_id,
                'plan_id' => (int) ($result['attempt']->metadata['plan_id'] ?? 0),
                'plan_version' => (int) ($result['attempt']->metadata['plan_version'] ?? 1),
                'reference_code' => $result['attempt']->reference_code,
                'amount' => $result['attempt']->amount,
                'currency' => $result['attempt']->currency,
                'status' => $result['attempt']->status,
                'provider' => $result['attempt']->provider,
                'checkout_url' => $result['checkout_url'],
                'qr_code' => $result['qr_code'],
                'created_at' => $result['attempt']->created_at?->toIso8601String(),
            ],
            'message' => 'Đã tạo hướng dẫn gia hạn. Trạng thái sẽ cập nhật sau khi máy chủ xác minh giao dịch.',
        ], 201);
    }

    private function paymentAttemptData(PlatformPaymentAttempt $attempt): array
    {
        $metadata = (array) ($attempt->metadata ?? []);

        return [
            'id' => $attempt->id,
            'organization_id' => $attempt->organization_id,
            'plan_id' => (int) ($metadata['plan_id'] ?? 0),
            'plan_version' => (int) ($metadata['plan_version'] ?? 1),
            'reference_code' => $attempt->reference_code,
            'amount' => (int) $attempt->amount,
            'currency' => $attempt->currency,
            'status' => $attempt->status,
            'provider' => $attempt->provider,
            'checkout_url' => $metadata['checkout_url'] ?? null,
            'qr_code' => $metadata['qr_code'] ?? null,
            'created_at' => $attempt->created_at?->toIso8601String(),
        ];
    }
}

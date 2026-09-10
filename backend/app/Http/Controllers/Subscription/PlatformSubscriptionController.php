<?php

namespace App\Http\Controllers\Subscription;

use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Domain\Subscription\Actions\ActivatePlatformTrial;
use App\Domain\Subscription\Actions\CancelSubscriptionRenewal;
use App\Domain\Subscription\Actions\ConfirmSubscriptionPayment;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSubscriptionController extends Controller
{
    public function current(
        Request $request,
        int $organizationId,
        PlatformSubscriptionRepositoryInterface $subRepo
    ): JsonResponse {
        $sub = $subRepo->findActiveForOrganization($organizationId);

        return response()->json([
            'data' => $sub ? PlatformSubscriptionResource::make($sub) : null,
            'has_active_subscription' => $sub !== null && $sub->isValid(),
            'has_used_trial' => $subRepo->hasUsedTrial($organizationId),
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
                'reference_code' => $result['attempt']->reference_code,
                'amount' => $result['attempt']->amount,
                'currency' => $result['attempt']->currency,
                'status' => $result['attempt']->status,
                'provider' => $result['attempt']->provider,
                'checkout_url' => $result['checkout_url'],
                'qr_code' => $result['qr_code'],
            ],
            'message' => 'Khởi tạo giao dịch thanh toán thành công. Vui lòng chuyển hướng sang cổng thanh toán.',
        ], 201);
    }

    public function confirmPayment(
        ConfirmSubscriptionPaymentRequest $request,
        ConfirmSubscriptionPayment $action
    ): JsonResponse {
        $result = $action->execute(
            referenceCode: (string) $request->validated('reference_code'),
            providerTransactionId: (string) $request->validated('provider_transaction_id'),
            amount: (int) $request->validated('amount'),
            currency: (string) $request->validated('currency'),
            rawPayload: $request->input('payload', []),
            actorId: $request->user()?->id
        );

        return response()->json([
            'data' => [
                'subscription' => PlatformSubscriptionResource::make($result['subscription']),
                'reference_code' => $result['attempt']->reference_code,
                'is_duplicate' => $result['is_duplicate'],
            ],
            'message' => $result['is_duplicate']
                ? 'Giao dịch đã được ghi nhận trước đó.'
                : 'Thanh toán thành công. Gói dịch vụ đã được kích hoạt.',
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
                'reference_code' => $result['attempt']->reference_code,
                'amount' => $result['attempt']->amount,
                'currency' => $result['attempt']->currency,
                'checkout_url' => $result['checkout_url'],
                'qr_code' => $result['qr_code'],
            ],
            'message' => 'Khởi tạo yêu cầu gia hạn thành công. Vui lòng hoàn tất thanh toán.',
        ], 201);
    }
}

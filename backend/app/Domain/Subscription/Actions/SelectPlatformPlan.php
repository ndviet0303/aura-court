<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformPlan;
use Illuminate\Validation\ValidationException;

class SelectPlatformPlan
{
    public function __construct(
        private readonly PlatformPlanRepositoryInterface $planRepo,
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
    ) {}

    /**
     * @return array{
     *     organization_id: int,
     *     plan: PlatformPlan,
     *     plan_id: int,
     *     plan_version: int,
     *     price: int,
     *     currency: string,
     *     billing_cycle: string,
     *     accepted_terms_version: string,
     *     trial_available: bool,
     *     trial_days: int
     * }
     */
    public function execute(int $organizationId, int $planId, int $actorId, string $acceptedTermsVersion): array
    {
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
            abort(403, 'Chỉ chủ sở hữu câu lạc bộ mới có quyền chọn gói dịch vụ nền tảng.');
        }

        $plan = $this->planRepo->findById($planId);
        if (! $plan || ! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => 'Gói dịch vụ không tồn tại hoặc đã ngừng cung cấp.',
            ]);
        }

        // Commercial configuration checks:
        // Missing commercial configuration must prevent activation rather than granting free access!
        if ($plan->price === null || ($plan->price <= 0 && ! $plan->trial_enabled)) {
            throw ValidationException::withMessages([
                'plan' => 'Gói dịch vụ chưa được cấu hình giá thương mại hợp lệ. Vui lòng liên hệ hỗ trợ nền tảng.',
            ]);
        }

        if (empty($plan->billing_cycle)) {
            throw ValidationException::withMessages([
                'plan' => 'Gói dịch vụ chưa được cấu hình chu kỳ thanh toán hợp lệ.',
            ]);
        }

        if (empty($plan->terms_version) || empty($plan->terms_text)) {
            throw ValidationException::withMessages([
                'terms' => 'Gói dịch vụ chưa có điều khoản và biểu phí được phê duyệt.',
            ]);
        }

        if ($acceptedTermsVersion !== (string) $plan->terms_version) {
            throw ValidationException::withMessages([
                'accepted_terms_version' => 'Phiên bản điều khoản đã thay đổi. Vui lòng đọc lại và đồng ý với điều khoản phiên bản '.$plan->terms_version.'.',
            ]);
        }

        $hasUsedTrial = $this->subRepo->hasUsedTrial($org->id);
        $trialAvailable = (bool) $plan->trial_enabled && (int) $plan->trial_days > 0 && ! $hasUsedTrial;

        return [
            'organization_id' => $org->id,
            'plan' => $plan,
            'plan_id' => $plan->id,
            'plan_version' => (int) $plan->version,
            'price' => (int) $plan->price,
            'currency' => $plan->currency ?? 'VND',
            'billing_cycle' => $plan->billing_cycle,
            'accepted_terms_version' => $acceptedTermsVersion,
            'trial_available' => $trialAvailable,
            'trial_days' => $trialAvailable ? (int) $plan->trial_days : 0,
        ];
    }
}

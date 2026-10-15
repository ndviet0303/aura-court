<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivatePlatformTrial
{
    public function __construct(
        private readonly PlatformPlanRepositoryInterface $planRepo,
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(int $organizationId, int $planId, int $actorId, string $acceptedTermsVersion): PlatformSubscription
    {
        return DB::transaction(function () use ($organizationId, $planId, $actorId, $acceptedTermsVersion) {
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
                abort(403, 'Chỉ chủ sở hữu câu lạc bộ mới có quyền kích hoạt dùng thử gói dịch vụ.');
            }

            $plan = $this->planRepo->findById($planId);
            if (! $plan || ! $plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' => 'Gói dịch vụ không tồn tại hoặc đã ngừng cung cấp.',
                ]);
            }

            if (! $plan->trial_enabled || (int) $plan->trial_days <= 0) {
                throw ValidationException::withMessages([
                    'plan' => 'Gói dịch vụ này không hỗ trợ chương trình dùng thử miễn phí.',
                ]);
            }

            if ($acceptedTermsVersion !== (string) $plan->terms_version) {
                throw ValidationException::withMessages([
                    'accepted_terms_version' => 'Phiên bản điều khoản đã thay đổi. Vui lòng xác nhận điều khoản phiên bản '.$plan->terms_version.'.',
                ]);
            }

            // Replay protection: An organization can only use trial once
            if ($this->subRepo->hasUsedTrial($org->id)) {
                throw ValidationException::withMessages([
                    'trial' => 'Câu lạc bộ đã từng sử dụng quyền lợi dùng thử. Vui lòng kích hoạt gói trả phí để tiếp tục sử dụng dịch vụ.',
                ]);
            }

            // Check if active subscription already exists
            $existingActive = $this->subRepo->findActiveForOrganizationWithLock($org->id);
            if ($existingActive && $existingActive->isValid()) {
                abort(409, 'Câu lạc bộ hiện đang có gói dịch vụ đang hoạt động.');
            }

            // Quota compliance check for existing resources
            $currentVenues = $org->venues()->count();
            $maxVenues = (int) ($plan->max_venues ?? 1);
            if ($currentVenues > $maxVenues) {
                throw ValidationException::withMessages([
                    'quota' => "Không thể kích hoạt gói {$plan->name}. Số lượng cơ sở hiện tại ({$currentVenues}) vượt quá giới hạn tối đa ({$maxVenues}) của gói này.",
                ]);
            }

            $currentCourts = $org->courts()->count();
            $maxCourts = (int) ($plan->max_courts ?? 4);
            if ($currentCourts > $maxCourts) {
                throw ValidationException::withMessages([
                    'quota' => "Không thể kích hoạt gói {$plan->name}. Số lượng sân hiện tại ({$currentCourts}) vượt quá giới hạn tối đa ({$maxCourts}) của gói này.",
                ]);
            }

            $currentStaff = $org->members()
                ->whereIn('role', ['staff'])
                ->whereIn('status', ['active', 'invited'])
                ->count();
            $maxStaff = (int) ($plan->max_staff ?? 2);
            if ($currentStaff > $maxStaff) {
                throw ValidationException::withMessages([
                    'quota' => "Không thể kích hoạt gói {$plan->name}. Số lượng nhân sự hiện tại ({$currentStaff}) vượt quá giới hạn tối đa ({$maxStaff}) của gói này.",
                ]);
            }

            $trialDurationDays = (int) $plan->trial_days;
            $trialEnds = now()->addDays($trialDurationDays);

            $subscription = $this->subRepo->create([
                'organization_id' => $org->id,
                'plan_id' => $plan->id,
                'plan_version' => (int) $plan->version,
                'status' => 'trial',
                'billing_cycle' => $plan->billing_cycle,
                'price' => (int) $plan->price,
                'currency' => $plan->currency ?? 'VND',
                'starts_at' => now(),
                'ends_at' => $trialEnds,
                'trial_starts_at' => now(),
                'trial_ends_at' => $trialEnds,
                'accepted_terms_version' => $acceptedTermsVersion,
                'terms_accepted_at' => now(),
                'auto_renew' => false,
            ]);

            Cache::increment('venues_version');

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'subscription.trial.activated',
                auditable: $subscription,
                metadata: [
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'trial_days' => $trialDurationDays,
                    'trial_ends_at' => $trialEnds->toIso8601String(),
                    'accepted_terms_version' => $acceptedTermsVersion,
                ]
            );

            return $subscription;
        });
    }
}

<?php

namespace App\Domain\Subscription\Actions;

use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnforcePlanQuota
{
    public function __construct(
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
    ) {}

    public function execute(int $organizationId, string $resourceType, int $additionalUnits = 1): void
    {
        DB::transaction(function () use ($organizationId, $resourceType, $additionalUnits) {
            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($organizationId);
            if (! $org) {
                abort(404, 'Câu lạc bộ / tổ chức không tồn tại.');
            }

            $sub = $this->subRepo->findActiveForOrganizationWithLock($organizationId);
            if (! $sub || ! $sub->isValid()) {
                throw ValidationException::withMessages([
                    'subscription' => 'Câu lạc bộ chưa có gói dịch vụ nền tảng hoạt động hợp lệ. Vui lòng kích hoạt gói dịch vụ để tiếp tục.',
                ]);
            }

            $plan = $sub->plan;
            if (! $plan) {
                throw ValidationException::withMessages([
                    'plan' => 'Không tìm thấy cấu hình gói dịch vụ đã đăng ký.',
                ]);
            }

            switch ($resourceType) {
                case 'venues':
                    $currentVenues = $org->venues()->count();
                    $maxVenues = (int) ($plan->max_venues ?? 1);
                    if (($currentVenues + $additionalUnits) > $maxVenues) {
                        throw ValidationException::withMessages([
                            'quota' => "Đã đạt giới hạn tối đa {$maxVenues} cơ sở thể thao của gói {$plan->name}. Vui lòng nâng cấp gói để tạo thêm cơ sở mới.",
                        ]);
                    }
                    break;

                case 'courts':
                    $currentCourts = $org->courts()->count();
                    $maxCourts = (int) ($plan->max_courts ?? 4);
                    if (($currentCourts + $additionalUnits) > $maxCourts) {
                        throw ValidationException::withMessages([
                            'quota' => "Đã đạt giới hạn tối đa {$maxCourts} sân thể thao của gói {$plan->name}. Vui lòng nâng cấp gói để tạo thêm sân mới.",
                        ]);
                    }
                    break;

                case 'staff':
                    $currentStaff = $org->members()
                        ->whereIn('role', ['venue_manager', 'staff', 'cashier'])
                        ->count();
                    $maxStaff = (int) ($plan->max_staff ?? 2);
                    if (($currentStaff + $additionalUnits) > $maxStaff) {
                        throw ValidationException::withMessages([
                            'quota' => "Đã đạt giới hạn tối đa {$maxStaff} nhân sự của gói {$plan->name}. Vui lòng nâng cấp gói để thêm nhân sự mới.",
                        ]);
                    }
                    break;

                default:
                    // Treat as feature gate (e.g. 'pos', 'advanced_analytics', 'online_booking')
                    if (! $plan->hasFeature($resourceType)) {
                        abort(403, "Tính năng {$resourceType} yêu cầu gói dịch vụ cao cấp hơn. Vui lòng nâng cấp gói dịch vụ để sử dụng.");
                    }
                    break;
            }
        });
    }
}

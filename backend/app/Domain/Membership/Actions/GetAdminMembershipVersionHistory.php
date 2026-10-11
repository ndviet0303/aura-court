<?php

namespace App\Domain\Membership\Actions;

use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use Illuminate\Support\Collection;

class GetAdminMembershipVersionHistory
{
    public function execute(int $organizationId): Collection
    {
        $plans = TenantMembershipPlan::where('organization_id', $organizationId)
            ->latest('updated_at')
            ->get();

        return $plans->map(function ($plan) use ($organizationId) {
            $activeUsers = TenantMembership::where('organization_id', $organizationId)
                ->where('plan_id', $plan->id)
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->count();

            $benefits = $plan->benefits ?? [];
            $changes = [];

            if (! empty($benefits['discount_percent'])) {
                $changes[] = 'Giảm giá sân '.$benefits['discount_percent'].'%';
            }
            if (! empty($benefits['point_multiplier'])) {
                $changes[] = 'Tích điểm x'.$benefits['point_multiplier'];
            }
            if (! empty($benefits['advance_booking_days'])) {
                $changes[] = 'Đặt sân trước '.$benefits['advance_booking_days'].' ngày';
            }
            if (! empty($benefits['perks']) && is_array($benefits['perks'])) {
                foreach (array_slice($benefits['perks'], 0, 2) as $p) {
                    $changes[] = $p;
                }
            }

            return [
                'id' => (string) $plan->id,
                'planId' => $plan->id,
                'planName' => $plan->name,
                'version' => 'v'.$plan->version.'.0',
                'status' => $plan->status === 'published' ? 'active' : 'archived',
                'updatedAt' => $plan->updated_at?->format('H:i, d/m/Y') ?? now()->format('H:i, d/m/Y'),
                'updatedBy' => 'Admin Quản Trị',
                'changes' => array_values(array_unique($changes)),
                'activeSubscribersCount' => $activeUsers,
                'note' => $plan->status === 'archived' ? 'Đã lưu trữ và bảo lưu quyền lợi cho hội viên cũ.' : null,
            ];
        });
    }
}

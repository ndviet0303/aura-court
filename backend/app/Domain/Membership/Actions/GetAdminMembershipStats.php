<?php

namespace App\Domain\Membership\Actions;

use App\Models\PointLedger;
use App\Models\TenantMembership;

class GetAdminMembershipStats
{
    public function execute(int $organizationId): array
    {
        $activeMemberships = TenantMembership::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->get();

        $totalVipMembers = $activeMemberships->count();

        // Calculate MRR from active memberships and plans
        $monthlyRecurringRevenue = (int) $activeMemberships->sum(function ($m) {
            $price = $m->plan?->price ?? 0;
            $cycle = $m->plan?->benefits['billing_cycle'] ?? 'monthly';
            if ($cycle === 'yearly') {
                return (int) round($price / 12);
            }
            if ($cycle === 'quarterly') {
                return (int) round($price / 3);
            }

            return (int) $price;
        });

        $issuedPoints = (int) PointLedger::where('organization_id', $organizationId)
            ->where('delta', '>', 0)
            ->sum('delta');

        $redeemedPoints = (int) abs(PointLedger::where('organization_id', $organizationId)
            ->where('delta', '<', 0)
            ->sum('delta'));

        return [
            'totalVipMembers' => $totalVipMembers,
            'monthlyRecurringRevenue' => $monthlyRecurringRevenue,
            'totalPointsIssued' => $issuedPoints,
            'redemptionRatePercent' => $issuedPoints > 0 ? round(($redeemedPoints / $issuedPoints) * 100, 1) : 0,
        ];
    }
}

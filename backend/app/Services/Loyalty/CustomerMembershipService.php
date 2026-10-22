<?php

namespace App\Services\Loyalty;

use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Database\Eloquent\Collection;

/** A customer's active tenant membership, for the profile and the wallet. Its benefits apply only at POS (design D21). */
class CustomerMembershipService
{
    private const DISCOUNT_CODES = ['court_discount_percent', 'booking_discount_percent', 'discount_percent'];

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function active(int $userId, ?int $organizationId): ?TenantMembership
    {
        if ($organizationId === null) {
            return null;
        }

        $now = now();

        return TenantMembership::query()->with([
            'plan',
            'entitlements' => fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now),
        ])
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereHas('plan', fn ($query) => $query->where('organization_id', $organizationId))
            ->latest('ends_at')
            ->first();
    }

    /**
     * The membership in the named organization; with none named, in the current tenant, else in the organization
     * of the customer's latest active membership, else in the customer's first active organization.
     */
    public function summary(User $user, mixed $organizationId): ?array
    {
        $organization = $organizationId
            ? Organization::query()->where('status', 'active')->find($organizationId)
            : $this->tenantContext->getOrganization();
        if (! $organization && ! $organizationId) {
            $membershipOrganizationId = TenantMembership::query()
                ->withoutGlobalScope('tenant_isolation')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->latest('ends_at')
                ->value('organization_id');
            $organization = $membershipOrganizationId
                ? Organization::query()->where('status', 'active')->find($membershipOrganizationId)
                : $user->organizations()->where('organizations.status', 'active')->first();
        }

        $hasTenantMembership = $organization && TenantMembership::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->exists();
        if (! $organization || (! $organization->hasMember($user->id) && ! $hasTenantMembership)) {
            return null;
        }

        $membership = $this->active((int) $user->id, (int) $organization->id);
        if (! $membership) {
            return null;
        }

        /** @var Collection<int, MembershipEntitlement> $entitlements */
        $entitlements = $membership->entitlements;
        $discount = $entitlements->first(fn (MembershipEntitlement $entitlement) => in_array($entitlement->benefit_code, self::DISCOUNT_CODES, true));
        // Stored JSON may be a bare number or an object, whatever the array cast says.
        $value = $discount?->getAttribute('benefit_value');
        $discountPercent = $discount
            ? (is_numeric($value) ? (int) $value : (int) ($value['percent'] ?? $value['discount_percent'] ?? $value['value'] ?? 0))
            : 0;

        return [
            'id' => (int) $membership->id,
            'user_id' => (int) $membership->user_id,
            'membership_plan_id' => (int) $membership->plan_id,
            'organization_id' => (int) $membership->organization_id,
            'status' => $membership->status,
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
            'plan' => [
                'id' => (int) $membership->plan_id,
                'name' => $membership->plan?->name,
                'discount_percent' => $discountPercent,
                'benefits' => $membership->plan->benefits ?? [],
            ],
            'entitlements' => $entitlements->map(fn (MembershipEntitlement $entitlement) => [
                'id' => (int) $entitlement->id,
                'benefit_code' => $entitlement->benefit_code,
                'benefit_value' => $entitlement->benefit_value,
                'starts_at' => $entitlement->starts_at,
                'ends_at' => $entitlement->ends_at,
            ])->values(),
        ];
    }
}

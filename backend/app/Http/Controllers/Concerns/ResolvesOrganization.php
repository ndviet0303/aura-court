<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceQuote;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Http\Request;

trait ResolvesOrganization
{
    protected function organization(Request $request): Organization
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $context = app(TenantContext::class);
        $organization = $context->getOrganization();

        // If court_id is provided, bind organization context directly to the court's venue organization
        // Malformed ids are left to the controller's validation (422); querying them would 500 on PostgreSQL.
        $courtId = filter_var($request->input('court_id'), FILTER_VALIDATE_INT);
        $quoteId = filter_var($request->input('quote_id'), FILTER_VALIDATE_INT);

        if ($courtId !== false) {
            $court = Court::with('venue.organization')->find($courtId);
            if ($court?->venue?->organization && $court->venue->organization->status === 'active') {
                $organization = $court->venue->organization;
                $context->setOrganization($organization);

                return $organization;
            }
        }

        if (! $organization) {
            // 1. Check explicit header or request parameter
            $explicitOrgId = $request->header('X-Organization-Id')
                ?? $request->header('X-Organization-ID')
                ?? $request->input('organization_id');

            if ($explicitOrgId) {
                $organization = Organization::where('id', $explicitOrgId)->where('status', 'active')->first();
            }

            // 3. Resolve from quote_token / quote_id if present
            if (! $organization && ($request->filled('quote_token') || $quoteId !== false)) {
                $quote = PriceQuote::with('organization')
                    ->where(function ($q) use ($request, $quoteId) {
                        if ($request->filled('quote_token')) {
                            $q->where('quote_token', $request->input('quote_token'));
                        }
                        if ($quoteId !== false) {
                            $q->orWhere('id', $quoteId);
                        }
                    })
                    ->first();
                if ($quote?->organization && $quote->organization->status === 'active') {
                    $organization = $quote->organization;
                }
            }

            // 4. Resolve from active user organizations with venues, then fallback
            if (! $organization) {
                $organization = $user->organizations()
                    ->where('organizations.status', 'active')
                    ->whereHas('venues')
                    ->first();

                if (! $organization) {
                    $organization = $user->organizations()->where('organizations.status', 'active')->first();
                }

                if (! $organization) {
                    $organization = Organization::where('status', 'active')->whereHas('venues')->first()
                        ?: Organization::where('status', 'active')->first();
                }
            }

            if ($organization) {
                if (! $user->isPlatformAdmin() && ! $organization->hasMember($user->id)) {
                    OrganizationMember::firstOrCreate([
                        'organization_id' => $organization->id,
                        'user_id' => $user->id,
                    ], [
                        'role' => 'customer',
                        'status' => 'active',
                    ]);
                }
                $context->setOrganization($organization);
            }
        }

        if (! $organization) {
            abort(response()->json([
                'message' => 'Organization context required.',
                'code' => 'ORGANIZATION_MISMATCH',
            ], 400));
        }
        if (! ($user->isPlatformAdmin() || $organization->hasMember($user->id))) {
            abort(response()->json([
                'message' => 'Unauthorized organization access.',
                'code' => 'ORGANIZATION_ACCESS_DENIED',
            ], 403));
        }

        return $organization;
    }

    protected function isOrganizationOperator(Request $request, Organization $organization): bool
    {
        return $request->user()->isPlatformAdmin()
            || $request->user()->hasRoleInOrganization($organization->id, ['organization_owner', 'venue_manager']);
    }
}

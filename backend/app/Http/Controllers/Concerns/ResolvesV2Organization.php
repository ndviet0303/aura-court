<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;
use App\Services\TenantContext;
use Illuminate\Http\Request;

trait ResolvesV2Organization
{
    protected function organization(Request $request): Organization
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $context = app(TenantContext::class);
        $organization = $context->getOrganization();
        if (! $organization) {
            $organization = $user->organizations()->where('organizations.status', 'active')->first();
            if ($organization) {
                $context->setOrganization($organization);
            }
        }

        abort_unless($organization, 400, 'Organization context required.');
        abort_unless($user->isPlatformAdmin() || $organization->hasMember($user->id), 403, 'Unauthorized organization access.');

        return $organization;
    }

    protected function isOrganizationOperator(Request $request, Organization $organization): bool
    {
        return $request->user()->isPlatformAdmin()
            || $request->user()->hasRoleInOrganization($organization->id, ['organization_owner', 'venue_manager']);
    }
}

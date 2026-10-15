<?php

namespace App\Http\Middleware;

use App\Services\Infrastructure\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-23: organization operations need a role in the request's organization; platform admin rights do not grant them.
 * A platform function shared with owners (dispute arbitration) lists the `platform_admin` pseudo-role explicitly.
 */
class RequireOrganizationRole
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }
        if (in_array('platform_admin', $roles, true) && $user->isPlatformAdmin()) {
            return $next($request);
        }

        $organization = $this->tenantContext->getOrganization();
        if (! $organization) {
            abort(400, 'Organization context required.');
        }

        if (! $user->hasRoleInOrganization($organization->id, $roles)) {
            abort(403, 'Insufficient organization role permissions.');
        }

        $request->attributes->set('verified_organization_id', $organization->id);

        return $next($request);
    }
}

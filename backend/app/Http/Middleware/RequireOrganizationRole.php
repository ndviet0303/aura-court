<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationRole
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        $organization = $this->tenantContext->getOrganization();
        if (! $organization) {
            abort(400, 'Organization context required.');
        }

        if (! $user->hasRoleInOrganization($organization->id, $roles)) {
            abort(403, 'Insufficient organization role permissions.');
        }

        return $next($request);
    }
}

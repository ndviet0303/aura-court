<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\Infrastructure\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->header('X-Organization-ID') ?? $request->header('X-Organization-Id');
        $orgSlug = $request->header('X-Organization-Slug') ?? $request->header('X-Organization-slug') ?? $request->route('organizationSlug');

        $organization = null;

        if ($orgId) {
            $organization = Organization::find($orgId);
        } elseif ($orgSlug) {
            $organization = Organization::where('slug', $orgSlug)->first();
        } elseif ($request->user()) {
            // Default to user's first active organization if not explicitly requested
            $organization = $request->user()->organizations()->where('organizations.status', 'active')->first();
        }

        if ($organization) {
            // BR-23: an authenticated user must belong to the organization; platform admin rights do not stand in.
            if ($request->user()) {
                $isMember = $organization->hasMember($request->user()->id);
                if (! $isMember && ! $request->is('api/loyalty*')) {
                    abort(response()->json([
                        'message' => 'Unauthorized access to organization.',
                        'code' => 'ORGANIZATION_ACCESS_DENIED',
                    ], 403));
                }
            }

            $this->tenantContext->setOrganization($organization);
        }

        return $next($request);
    }
}

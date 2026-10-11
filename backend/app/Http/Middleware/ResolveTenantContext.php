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
            // If user is authenticated, ensure they belong to this organization or are platform admin
            if ($request->user() && ! $request->user()->isPlatformAdmin()) {
                $isMember = $organization->hasMember($request->user()->id);
                if (! $isMember && ! $request->is('api/v1/courts*') && ! $request->is('api/v1/availability*') && ! $request->is('api/v2/loyalty*')) {
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

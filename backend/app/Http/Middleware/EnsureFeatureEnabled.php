<?php

namespace App\Http\Middleware;

use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureFlagService $features, private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        $this->features->assertEnabled($flag, $this->tenantContext->getOrganizationId());

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAdminOrStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        $hasActiveRole = $user->organizationMembers()
            ->where('status', 'active')
            ->whereIn('role', [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_VENUE_MANAGER,
                OrganizationMember::ROLE_STAFF,
                OrganizationMember::ROLE_CASHIER,
            ])
            ->exists();

        abort_unless($hasActiveRole, 403, 'Organization admin or staff access required.');

        return $next($request);
    }
}

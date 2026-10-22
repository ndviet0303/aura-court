<?php

namespace App\Http\Controllers\Concerns;

use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Http\Request;

/**
 * Organization-scoped venue reads (BR-20, BR-25): the owner reads every venue of the organization, staff only the
 * venues assigned to them. The using controller injects VenueLifecycleRepositoryInterface as $venueRepo.
 */
trait AuthorizesVenueRead
{
    /** A venue is read only through its own organization, by the owner or by staff assigned to it. */
    private function authorizeVenueRead(Request $request, int $organizationId, int $venueId): Venue
    {
        // Membership first, so an outsider cannot tell from 403 vs 404 which organization a venue id belongs to
        $this->authorizeOrganizationRead($request, $organizationId);
        $venue = $this->venueRepo->findById($venueId);
        abort_if($venue === null || (int) $venue->organization_id !== $organizationId, 404, 'Cơ sở thể thao không tồn tại.');
        abort_unless($request->user()->canOperateVenue($venue), 403, 'Bạn không có quyền xem cơ sở này.');

        return $venue;
    }

    private function authorizeOrganizationRead(Request $request, int $organizationId): void
    {
        abort_unless($request->user()->hasRoleInOrganization($organizationId, [
            OrganizationMember::ROLE_ORGANIZATION_OWNER,
            OrganizationMember::ROLE_STAFF,
        ]), 403, 'Bạn không có quyền xem cơ sở của tổ chức này.');
    }
}

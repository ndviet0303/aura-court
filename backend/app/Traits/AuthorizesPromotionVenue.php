<?php

namespace App\Traits;

use App\Models\Venue;
use Illuminate\Http\Request;

/**
 * Design D25: promotion programs are managed by the owner of the venue's organization, acting in that organization.
 * The role middleware only checks the role in the X-Organization-ID organization, so a venue of another organization
 * the user also owns is refused too. Shared by the program controller and form requests.
 */
trait AuthorizesPromotionVenue
{
    protected function managesPromotionVenue(Request $request, Venue $venue): bool
    {
        return (int) $venue->organization_id === (int) $request->attributes->get('verified_organization_id')
            && (bool) $request->user()?->canManageVenue($venue);
    }
}

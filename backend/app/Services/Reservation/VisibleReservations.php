<?php

namespace App\Services\Reservation;

use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * The reservations a signed-in user may open: their own in any organization, or one at a venue of the context
 * organization they operate.
 */
class VisibleReservations
{
    /** Find one by id or code, or 404. Binds the tenant context to the record's organization. */
    public function find(User $user, Organization $organization, string $identifier): Reservation
    {
        $query = Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->where(fn ($q) => $this->scope($q, $user, $organization));
        if (ctype_digit($identifier)) {
            $query->whereKey((int) $identifier);
        } else {
            $query->where('code', $identifier);
        }
        $record = $query->firstOrFail();
        $recordOrganization = $record->organization;
        abort_unless($recordOrganization instanceof Organization, 404);
        app(TenantContext::class)->setOrganization($recordOrganization);

        return $record;
    }

    /**
     * BR-21: the owner manages every booking of the organization, staff only those at their assigned venues.
     *
     * @param  Builder<Reservation>  $query
     */
    public function scope(Builder $query, User $user, Organization $organization): void
    {
        $venueIds = $user->getAuthorizedVenueIds($organization->id);
        $query->where('user_id', $user->id)
            ->orWhere(fn ($q) => $q->where('organization_id', $organization->id)
                ->whereHas('court', fn ($court) => $court->whereIn('venue_id', $venueIds)));
    }
}

<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting a dated exception, or previewing that deletion; only the owner changes the venue's hours. */
class DeleteVenueScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $venue = Venue::find($this->route('venueId'));

        return $user !== null
            && $venue !== null
            && (int) $venue->organization_id === (int) $this->route('organizationId')
            && $user->canManageVenue($venue)
            && $user->hasRoleInOrganization($venue->organization_id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]);
    }

    public function rules(): array
    {
        return [
            'acknowledge_affected' => ['sometimes', 'boolean'],
        ];
    }
}

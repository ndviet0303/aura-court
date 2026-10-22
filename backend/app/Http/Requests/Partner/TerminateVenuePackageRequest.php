<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;

class TerminateVenuePackageRequest extends FormRequest
{
    /** Owner of the path organization; the action answers 404 for a venue outside it. */
    public function authorize(): bool
    {
        return $this->user()?->hasRoleInOrganization((int) $this->route('organizationId'), OrganizationMember::ROLE_ORGANIZATION_OWNER) === true;
    }

    public function rules(): array
    {
        // Anything but true is answered by the action with the coded TERMINATION_NOT_CONFIRMED, not a plain 422.
        return [
            'confirm_consequences' => ['nullable'],
        ];
    }
}

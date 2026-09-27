<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceivingConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organizationId = (int) $this->route('organizationId');
        $venue = Venue::query()
            ->where('organization_id', $organizationId)
            ->find($this->route('venueId'));

        return $user !== null
            && $venue !== null
            && $user->hasRoleInOrganization($organizationId, OrganizationMember::ROLE_ORGANIZATION_OWNER)
            && $user->canManageVenue($venue);
    }

    public function rules(): array
    {
        return [
            'bank_code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'account_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'regex:/^\d{4,34}$/'],
            'legal_owner_confirmed' => ['required', 'accepted'],
            'bank_password' => ['prohibited'],
            'bank_login_password' => ['prohibited'],
        ];
    }
}

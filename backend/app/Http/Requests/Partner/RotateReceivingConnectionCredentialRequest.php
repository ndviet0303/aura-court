<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use App\Models\ReceivingConnection;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

class RotateReceivingConnectionCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organizationId = (int) $this->route('organizationId');
        $venue = Venue::query()
            ->where('organization_id', $organizationId)
            ->find($this->route('venueId'));
        $connection = ReceivingConnection::query()
            ->where('organization_id', $organizationId)
            ->where('venue_id', $this->route('venueId'))
            ->find($this->route('connectionId'));

        return $user !== null
            && $venue !== null
            && $connection !== null
            && $user->hasRoleInOrganization($organizationId, OrganizationMember::ROLE_ORGANIZATION_OWNER)
            && $user->canManageVenue($venue);
    }

    public function rules(): array
    {
        return [
            'hmac_secret' => ['required', 'string', 'min:32', 'max:512'],
            'bank_password' => ['prohibited'],
            'bank_login_password' => ['prohibited'],
            'api_key' => ['prohibited'],
        ];
    }
}

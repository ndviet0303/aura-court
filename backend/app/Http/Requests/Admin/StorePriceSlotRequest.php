<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $court = $this->route('court');
        if ($court) {
            return $user->canManageVenue($court->venue_id);
        }

        return $user->organizationMembers()
            ->where('status', 'active')
            ->whereIn('role', [
                \App\Models\OrganizationMember::ROLE_ORGANIZATION_OWNER,
                \App\Models\OrganizationMember::ROLE_VENUE_MANAGER,
            ])
            ->exists();
    }

    public function rules(): array
    {
        return [
            'weekday' => 'required|integer|min:1|max:7',
            'starts_at' => 'required|date_format:H:i',
            'ends_at' => 'required|date_format:H:i',
            'price' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}

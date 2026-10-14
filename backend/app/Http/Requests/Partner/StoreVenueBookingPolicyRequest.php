<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVenueBookingPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $venue = $this->route('venueId') ? Venue::find($this->route('venueId')) : null;

        return $user !== null
            && $venue !== null
            && (int) $venue->organization_id === (int) $this->route('organizationId')
            && $user->canManageVenue($venue)
            && $user->hasRoleInOrganization($venue->organization_id, [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_VENUE_MANAGER,
            ]);
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:booking,arrival,recurring,transfer'],
            'settings.booking' => ['required', 'array:hold_ttl_minutes,max_days_ahead,min_duration_minutes,max_duration_minutes,require_continuity'],
            'settings.booking.hold_ttl_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'settings.booking.max_days_ahead' => ['required', 'integer', 'min:1', 'max:365'],
            'settings.booking.min_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'settings.booking.max_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'settings.booking.require_continuity' => ['required', 'boolean'],
            'settings.arrival' => ['required', 'array:early_minutes,late_grace_minutes,no_show_after_minutes'],
            'settings.arrival.early_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'settings.arrival.late_grace_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'settings.arrival.no_show_after_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'settings.recurring' => ['required', 'array:payment_policy,conflict_policy,overdue_release_hours,guarantee_confirm'],
            'settings.recurring.payment_policy' => ['required', 'in:full_series,per_period'],
            'settings.recurring.conflict_policy' => ['required', 'in:all_or_nothing,partial'],
            'settings.recurring.overdue_release_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'settings.recurring.guarantee_confirm' => ['sometimes', 'boolean'],
            'settings.transfer' => ['required', 'array:cutoff_hours,max_consideration_percent,free_transfer_allowed'],
            'settings.transfer.cutoff_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'settings.transfer.max_consideration_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'settings.transfer.free_transfer_allowed' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $settings = $this->input('settings', []);

            $minimum = $settings['booking']['min_duration_minutes'] ?? null;
            $maximum = $settings['booking']['max_duration_minutes'] ?? null;
            if (is_numeric($minimum) && is_numeric($maximum) && (float) $minimum > (float) $maximum) {
                $validator->errors()->add('settings.booking.max_duration_minutes', 'Maximum duration must be at least the minimum duration.');
            }
        });
    }
}

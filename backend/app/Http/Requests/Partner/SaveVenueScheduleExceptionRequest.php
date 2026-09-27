<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveVenueScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $venue = Venue::find($this->route('venueId'));
        $courtId = $this->input('court_id');

        return $user !== null
            && $venue !== null
            && (int) $venue->organization_id === (int) $this->route('organizationId')
            && ($courtId === null || $venue->courts()->whereKey($courtId)->exists())
            && ($user->isPlatformAdmin() || ($user->canManageVenue($venue)
                && $user->hasRoleInOrganization($venue->organization_id, [
                    OrganizationMember::ROLE_ORGANIZATION_OWNER,
                    OrganizationMember::ROLE_VENUE_MANAGER,
                ])));
    }

    public function rules(): array
    {
        return [
            'court_id' => ['nullable', 'integer'],
            'exception_date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:holiday,special_hours,closed'],
            'timezone' => ['required', 'timezone'],
            'intervals' => ['nullable', 'array'],
            'intervals.*.start' => ['required', 'date_format:H:i'],
            'intervals.*.end' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $intervals = $this->input('intervals', []);
            $type = $this->input('type');
            if ($type === 'special_hours' && count($intervals) === 0) {
                $validator->errors()->add('intervals', 'Special hours must include at least one operating interval.');
            }

            $sorted = collect($intervals)->sortBy('start')->values();
            foreach ($sorted as $index => $interval) {
                if (($interval['end'] ?? '') <= ($interval['start'] ?? '')) {
                    $validator->errors()->add("intervals.{$index}.end", 'End time must be later than start time.');
                }
                if ($index > 0 && ($interval['start'] ?? '') < ($sorted[$index - 1]['end'] ?? '')) {
                    $validator->errors()->add("intervals.{$index}.start", 'Operating intervals must not overlap.');
                }
            }
        });
    }
}

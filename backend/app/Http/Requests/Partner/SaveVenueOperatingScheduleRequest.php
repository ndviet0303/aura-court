<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveVenueOperatingScheduleRequest extends FormRequest
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
            'timezone' => ['required', 'timezone'],
            'weekly_hours' => ['required', 'array'],
            'weekly_hours.1' => ['required', 'array:closed,intervals'],
            'weekly_hours.2' => ['required', 'array:closed,intervals'],
            'weekly_hours.3' => ['required', 'array:closed,intervals'],
            'weekly_hours.4' => ['required', 'array:closed,intervals'],
            'weekly_hours.5' => ['required', 'array:closed,intervals'],
            'weekly_hours.6' => ['required', 'array:closed,intervals'],
            'weekly_hours.7' => ['required', 'array:closed,intervals'],
            'weekly_hours.*.closed' => ['required', 'boolean'],
            'weekly_hours.*.intervals' => ['present', 'array'],
            'weekly_hours.*.intervals.*.start' => ['required', 'date_format:H:i'],
            'weekly_hours.*.intervals.*.end' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('weekly_hours', []) as $day => $schedule) {
                $intervals = $schedule['intervals'] ?? [];
                if (! ($schedule['closed'] ?? false) && count($intervals) === 0) {
                    $validator->errors()->add("weekly_hours.{$day}.intervals", 'An open day must contain at least one operating interval.');
                }

                $sorted = collect($intervals)->sortBy('start')->values();
                foreach ($sorted as $index => $interval) {
                    if (($interval['end'] ?? '') <= ($interval['start'] ?? '')) {
                        $validator->errors()->add("weekly_hours.{$day}.intervals.{$index}.end", 'End time must be later than start time.');
                    }
                    if ($index > 0 && ($interval['start'] ?? '') < ($sorted[$index - 1]['end'] ?? '')) {
                        $validator->errors()->add("weekly_hours.{$day}.intervals.{$index}.start", 'Operating intervals must not overlap.');
                    }
                }
            }
        });
    }
}

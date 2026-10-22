<?php

namespace App\Http\Requests\Partner;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\Rules\ServiceIntervalRules;
use App\Models\OrganizationMember;
use App\Models\Venue;
use App\Services\Reservation\VenueOperatingCalendar;
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
            && (($user->canManageVenue($venue)
                && $user->hasRoleInOrganization($venue->organization_id, [
                    OrganizationMember::ROLE_ORGANIZATION_OWNER,
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
            // The interval checks read well-formed H:i times only.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            // BR-31: a court schedule lies inside the venue's weekly hours; the venue's own hours have no outer bound.
            $forCourt = $this->input('court_id') !== null;
            $venueHours = $forCourt ? app(OperationalStatusRepositoryInterface::class)->venueSchedule((int) $this->route('venueId')) : null;
            foreach ($this->input('weekly_hours') as $day => $schedule) {
                if ($schedule['closed']) {
                    continue;
                }
                $label = ServiceIntervalRules::WEEKDAYS[(int) $day] ?? "Thứ {$day}";
                if ($schedule['intervals'] === []) {
                    $validator->errors()->add("weekly_hours.{$day}.intervals", "{$label}: ngày mở cửa phải có ít nhất một khung giờ.");

                    continue;
                }
                $venueMinutes = $forCourt ? VenueOperatingCalendar::openMinutes($venueHours, null, (int) $day) : null;
                foreach (ServiceIntervalRules::errors($schedule['intervals'], "weekly_hours.{$day}.intervals", $label, $venueMinutes) as [$field, $message]) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }
}

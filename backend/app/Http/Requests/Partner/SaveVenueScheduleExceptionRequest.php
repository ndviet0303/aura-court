<?php

namespace App\Http\Requests\Partner;

use App\Domain\Venue\Rules\ServiceIntervalRules;
use App\Models\OrganizationMember;
use App\Models\Venue;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\CarbonImmutable;
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
            && (($user->canManageVenue($venue)
                && $user->hasRoleInOrganization($venue->organization_id, [
                    OrganizationMember::ROLE_ORGANIZATION_OWNER,
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
            // The interval checks read a well-formed date and H:i times only.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $type = $this->input('type');
            $intervals = $this->input('intervals') ?? [];
            if ($type === 'special_hours' && $intervals === []) {
                $validator->errors()->add('intervals', 'Giờ đặc biệt phải có ít nhất một khung giờ.');
            }
            // A closed day ignores its intervals.
            if ($type === 'closed') {
                return;
            }
            $date = (string) $this->input('exception_date');
            // BR-31: a court exception lies inside the venue's hours of that date; a venue exception has no outer bound.
            $venueMinutes = $this->input('court_id') === null
                ? null
                : app(VenueOperatingCalendar::class)->venueMinutesOn((int) $this->route('venueId'), $date);
            $day = 'Ngày '.CarbonImmutable::parse($date)->format('d/m/Y');
            foreach (ServiceIntervalRules::errors($intervals, 'intervals', $day, $venueMinutes) as [$field, $message]) {
                $validator->errors()->add($field, $message);
            }
        });
    }
}

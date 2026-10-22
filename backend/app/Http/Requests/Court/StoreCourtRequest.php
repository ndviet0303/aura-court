<?php

namespace App\Http\Requests\Court;

use App\Domain\Venue\Rules\CourtInformationRules;
use App\Domain\Venue\Rules\VenueInformationRules;
use App\Models\Court;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** BR-27, D9, D11: a new court of a venue the caller manages; CourtController::store creates it inactive. */
class StoreCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venueId = $this->venueId();

        // Another organization's venue is refused before the code check reads its courts; a missing or malformed
        // venue_id is left to the rules (422).
        return $venueId === null || (bool) $this->user()?->canManageVenue($venueId);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(VenueInformationRules::trim($this->only(['code', 'description'])));
    }

    public function rules(): array
    {
        return [
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')],
            'sport_type' => ['required', Rule::in(Court::SPORT_TYPES)],
            // No venue id 0 exists, so a venue_id the rules reject checks the code against nothing.
            ...CourtInformationRules::rules($this->venueId() ?? 0),
        ];
    }

    public function messages(): array
    {
        return CourtInformationRules::messages();
    }

    /**
     * The venue id as the "integer" rule accepts it, else null. authorize() and the code check read the same value,
     * so a value such as "12a" (cast to 12) cannot probe the court codes of a venue the caller does not manage.
     */
    private function venueId(): ?int
    {
        $venueId = filter_var($this->input('venue_id'), FILTER_VALIDATE_INT);

        return $venueId === false ? null : $venueId;
    }
}

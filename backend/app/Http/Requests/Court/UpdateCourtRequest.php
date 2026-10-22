<?php

namespace App\Http\Requests\Court;

use App\Domain\Venue\Rules\CourtInformationRules;
use App\Domain\Venue\Rules\VenueInformationRules;
use App\Models\Court;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D10: an update changes only the code, description and images, plus the retained surface, capacity, slot length
 * and amenities. Venue and sport are fixed after creation: resending the stored value passes, another value is a
 * 422 rather than being ignored, so the client never believes it moved the court.
 */
class UpdateCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $court = $this->route('court');

        return $court instanceof Court && (bool) $this->user()?->canManageVenue((int) $court->venue_id);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(VenueInformationRules::trim($this->only(['code', 'description'])));
    }

    public function rules(): array
    {
        /** @var Court $court */
        $court = $this->route('court');
        $editable = CourtInformationRules::rules((int) $court->venue_id, (int) $court->id);

        return [
            'venue_id' => [self::unchanged((string) $court->venue_id, 'Không thể chuyển sân sang cơ sở khác.')],
            'sport_type' => [self::unchanged((string) $court->sport_type, 'Không thể đổi môn thể thao của sân.')],
            ...array_map(fn (array $rules): array => ['sometimes', ...$rules], $editable),
        ];
    }

    public function messages(): array
    {
        return CourtInformationRules::messages();
    }

    private static function unchanged(string $stored, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($stored, $message): void {
            if (! is_scalar($value) || (string) $value !== $stored) {
                $fail($message);
            }
        };
    }
}

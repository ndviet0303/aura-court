<?php

namespace App\Http\Requests\Venue;

use App\Domain\Venue\Rules\VenueInformationRules;
use App\Models\OrganizationMember;
use App\Rules\StoredImageUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D12, BR-26: the one validation of venue create (POST) and information update (PUT) on the partner write path.
 * Only the owner of the organization on the path saves; the BR-26 fields come from VenueInformationRules, the
 * same source OpenVenue and ResumeVenue check.
 */
class SaveVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('venueId') !== null) {
            return LifecycleCommandRequest::ownsRouteVenue($this);
        }

        return (bool) $this->user()?->hasRoleInOrganization(
            (int) $this->route('organizationId'),
            OrganizationMember::ROLE_ORGANIZATION_OWNER,
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(VenueInformationRules::trim($this->all()));
    }

    public function rules(): array
    {
        return VenueInformationRules::rules() + [
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'google_maps_url' => ['nullable', 'url', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                // The closure runs even after "url" fails, so an array must not reach a string cast (500).
                if (! is_string($value) || ! self::isGoogleMapsUrl($value)) {
                    $fail('Chỉ chấp nhận liên kết Google Maps HTTPS hợp lệ.');
                }
            }],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['string', new StoredImageUrl],
        ];
    }

    public function messages(): array
    {
        return VenueInformationRules::messages() + ['images' => 'Mỗi cơ sở tối đa 10 ảnh.'];
    }

    private static function isGoogleMapsUrl(string $value): bool
    {
        $parts = parse_url($value);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        if ($host === 'maps.app.goo.gl') {
            return true;
        }
        if ($host === 'goo.gl') {
            return str_starts_with($path, '/maps');
        }

        return (bool) preg_match('/^(?:(?:www|maps)\.)?google\.(?:[a-z]{2,3}|(?:com|co)\.[a-z]{2})$/', $host)
            && (bool) preg_match('#^/maps(?:/|$)#', $path);
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreVenueRequest extends FormRequest
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
            'name' => 'required|array',
            'name.*' => 'string|max:120',
            'address' => 'required|string|max:255',
            'google_maps_url' => 'required|url|max:2048',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $url = $this->input('google_maps_url');
            if ($url && ! $this->isGoogleMapsUrl($url)) {
                $validator->errors()->add('google_maps_url', 'Chỉ chấp nhận liên kết Google Maps HTTPS hợp lệ.');
            }
        });
    }

    private function isGoogleMapsUrl(string $value): bool
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

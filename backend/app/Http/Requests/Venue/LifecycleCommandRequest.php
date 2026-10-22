<?php

namespace App\Http\Requests\Venue;

use App\Models\Court;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Open and resume of a venue (partner route) or a court (admin route); no body. BR-34: only the owner opens,
 * and resumes a venue; assigned staff may also resume a court, which ResumeCourt limits to a maintenance pause.
 */
class LifecycleCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $court = $this->route('court');
        if (! $court instanceof Court) {
            return self::ownsRouteVenue($this);
        }

        $user = $this->user();

        return $user !== null && ($this->route()?->getActionMethod() === 'resume'
            ? $user->canOperateVenue((int) $court->venue_id)
            : $user->canManageVenue((int) $court->venue_id));
    }

    public function rules(): array
    {
        return [];
    }

    /** The caller owns the venue on the partner route, inside the organization the path names. */
    public static function ownsRouteVenue(FormRequest $request): bool
    {
        $user = $request->user();
        $venue = Venue::find($request->route('venueId'));

        return $user !== null
            && $venue !== null
            && (int) $venue->organization_id === (int) $request->route('organizationId')
            && $user->canManageVenue($venue);
    }
}

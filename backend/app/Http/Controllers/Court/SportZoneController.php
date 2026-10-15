<?php

namespace App\Http\Controllers\Court;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Http\Controllers\Controller;
use App\Models\SportZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SportZoneController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $scopedVenueIds = null;
        $isAdminRoute = $request->is('api/admin/sport-zones');
        if ($isAdminRoute && $user) {
            $authorizedVenueIds = $user->getAuthorizedVenueIds();
            if ($request->filled('venue_id')) {
                $requestedVenueId = (int) $request->query('venue_id');
                abort_unless(in_array($requestedVenueId, $authorizedVenueIds, true), 403, 'Unauthorized venue.');
                $scopedVenueIds = [$requestedVenueId];
            } else {
                $scopedVenueIds = $authorizedVenueIds;
            }
        } elseif ($request->filled('venue_id')) {
            $scopedVenueIds = [(int) $request->query('venue_id')];
        }

        $version = Cache::get('sport_zones_version', 1);
        $cacheKey = "sport_zones:v{$version}:".md5(json_encode([$isAdminRoute, $scopedVenueIds, $request->only(['sport_type'])]));

        $zones = Cache::remember($cacheKey, 300, function () use ($request, $scopedVenueIds, $isAdminRoute) {
            return SportZone::query()
                ->when(! $isAdminRoute, fn ($q) => $q->where('is_active', true)->whereHas('venue', fn ($venue) => $venue->where('is_active', true)))
                ->when($scopedVenueIds !== null, fn ($q) => $q->whereIn('venue_id', $scopedVenueIds))
                ->when($request->query('sport_type'), fn ($q, $sportType) => $q->where('sport_type', $sportType))
                ->withCount('courts')
                ->with(['venue'])
                ->orderBy('id')
                ->get()
                ->toArray();
        });

        if (! $isAdminRoute) {
            $eligibility = app(VerifyEffectiveBookingEligibility::class);
            $zones = collect($zones)
                ->filter(fn (array $zone) => $eligibility->evaluateVenue((int) $zone['venue_id'])['eligible'])
                ->values();
        }

        return response()->json($zones);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'name' => 'required|array',
            'name.*' => 'string|max:120',
            'sport_type' => 'required|string|in:badminton,football_5v5,football_7v7,tennis,pickleball,basketball,volleyball,table_tennis',
            'slot_duration' => 'required|integer|in:30,45,60,90,120',
            'description' => 'nullable|array',
            'description.*' => 'string',
            'is_active' => 'boolean',
        ]);
        $user = $request->user();
        if ($user) {
            abort_unless($user->canManageVenue((int) $data['venue_id']), 403, 'Unauthorized venue.');
        }

        $zone = SportZone::create($data);
        Cache::increment('sport_zones_version');

        return response()->json($zone->load('venue'), 201);
    }

    public function show(Request $request, SportZone $sportZone)
    {
        if (! $request->is('api/admin/sport-zones/*')) {
            abort_unless($sportZone->is_active, 404);
            abort_unless(app(VerifyEffectiveBookingEligibility::class)->evaluateVenue($sportZone->venue_id)['eligible'], 404);
        }

        return $sportZone->load(['venue', 'courts.priceSlots']);
    }

    public function update(Request $request, SportZone $sportZone)
    {
        $user = $request->user();
        if ($user) {
            abort_unless($user->canManageVenue($sportZone->venue_id), 403, 'Unauthorized venue.');
        }

        $data = $request->validate([
            'name' => 'sometimes|array',
            'name.*' => 'string|max:120',
            'sport_type' => 'sometimes|string|in:badminton,football_5v5,football_7v7,tennis,pickleball,basketball,volleyball,table_tennis',
            'slot_duration' => 'sometimes|integer|in:30,45,60,90,120',
            'description' => 'nullable|array',
            'description.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $sportZone->update($data);
        Cache::increment('sport_zones_version');

        return $sportZone->fresh(['venue']);
    }

    public function destroy(SportZone $sportZone)
    {
        $user = request()->user();
        if ($user) {
            abort_unless($user->canManageVenue($sportZone->venue_id), 403, 'Unauthorized venue.');
        }

        $sportZone->update(['is_active' => false]);
        Cache::increment('sport_zones_version');

        return response()->json(['message' => 'Đã ngừng hoạt động khu vực thể thao.']);
    }
}

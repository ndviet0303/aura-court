<?php

namespace App\Http\Controllers\Court;

use App\Domain\Subscription\Actions\EnforcePlanQuota;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Venue;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Reservation\CourtPriceRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourtController extends Controller
{
    public function index(Request $request)
    {
        // Database cache counters must exist before mutations can increment them.
        Cache::add('courts_version', 1, now()->addYears(10));
        Cache::add('venues_version', 1, now()->addYears(10));
        $courtsVersion = Cache::get('courts_version', 1);
        $venuesVersion = Cache::get('venues_version', 1);
        $cacheKey = "courts:c{$courtsVersion}:v{$venuesVersion}:".md5(json_encode($request->only(['sport_type', 'venue_id', 'sport_zone_id'])));
        $courts = Cache::remember($cacheKey, 300, function () use ($request) {
            $courtsQuery = Court::where('is_active', true)
                ->whereHas('venue', fn ($query) => $query->where('is_active', true))
                ->when($request->query('sport_type'), function ($q, $sportType) {
                    $q->whereHas('sportZone', fn ($zone) => $zone->where('sport_type', $sportType)->where('is_active', true));
                })
                ->when($request->query('venue_id'), function ($q, $venueId) {
                    $q->where('venue_id', $venueId);
                })
                ->when($request->query('sport_zone_id'), function ($q, $zoneId) {
                    $q->where('sport_zone_id', $zoneId);
                })
                ->with([
                    'priceSlots' => fn ($q) => $q->select(['id', 'court_id', 'price', 'weekday', 'starts_at', 'ends_at'])->where('is_active', true),
                    'venue.organization',
                    'sportZone',
                ])
                ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('is_published', true)])
                ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->where('is_published', true)], 'rating')
                ->orderBy('id')
                ->get();

            $eligibility = app(VerifyEffectiveBookingEligibility::class);
            $features = app(FeatureFlagService::class);
            $venueEligibilityCache = [];
            $filteredCourts = [];
            $pricedCourtIds = app(CourtPriceRuleService::class)->pricedCourtIds($courtsQuery);

            foreach ($courtsQuery as $court) {
                $venueId = (int) $court->venue_id;
                if (! isset($venueEligibilityCache[$venueId])) {
                    $venueEligibilityCache[$venueId] = $court->venue
                        ? $eligibility->evaluateVenue($court->venue)['eligible']
                        : false;
                }

                if ($venueEligibilityCache[$venueId] && $court->is_active && isset($pricedCourtIds[$court->id])) {
                    $courtArray = $court->toArray();
                    $organizationId = (int) ($courtArray['venue']['organization_id'] ?? 0);
                    $courtArray['venue']['payment_model'] = $features->enabled('direct_owner_payments', $organizationId)
                        ? 'direct_owner_v1'
                        : 'legacy_wallet_v1';
                    $filteredCourts[] = $courtArray;
                }
            }

            return $filteredCourts;
        });

        return response()->json($courts);
    }

    public function show(Court $court)
    {
        $result = app(VerifyEffectiveBookingEligibility::class)->execute($court);
        abort_unless($result['eligible'], 404, 'Sân không tồn tại hoặc đã ngừng hoạt động.');
        $court = $result['court'];
        $court->loadMissing(['priceSlots', 'venue', 'sportZone'])
            ->loadCount(['reviews as reviews_count' => fn ($query) => $query->where('is_published', true)])
            ->loadAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->where('is_published', true)], 'rating');
        $organizationId = (int) ($court->venue?->organization_id ?? 0);
        $court->venue?->setAttribute(
            'payment_model',
            app(FeatureFlagService::class)->enabled('direct_owner_payments', $organizationId)
                ? 'direct_owner_v1'
                : 'legacy_wallet_v1'
        );

        return response()->json($court);
    }

    public function adminIndex(Request $request)
    {
        $user = $request->user();
        $scopedVenueIds = null;
        if ($user) {
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

        $query = Court::with([
            'venue',
            'sportZone',
            'blocks' => fn ($query) => $query->latest('starts_at'),
        ])
            ->withCount('priceSlots')
            ->when($scopedVenueIds !== null, fn ($q) => $q->whereIn('venue_id', $scopedVenueIds))
            ->when($request->query('sport_zone_id'), fn ($q, $zoneId) => $q->where('sport_zone_id', $zoneId))
            ->when($request->query('sport_type'), fn ($q, $sportType) => $q->whereHas('sportZone', fn ($z) => $z->where('sport_type', $sportType)))
            ->orderBy('id');

        if ($request->boolean('include_prices') || ($scopedVenueIds !== null && count($scopedVenueIds) === 1)) {
            $query->with(['priceSlots' => fn ($q) => $q->select(['id', 'court_id', 'weekday', 'starts_at', 'ends_at', 'price'])]);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => ['vi' => $request->input('name'), 'en' => $request->input('name')]]);
        }
        if (is_string($request->input('description'))) {
            $request->merge(['description' => ['vi' => $request->input('description'), 'en' => $request->input('description')]]);
        }

        $data = $request->validate([
            'name' => 'required|array',
            'name.*' => 'string|max:120',
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'sport_zone_id' => ['nullable', 'integer', Rule::exists('sport_zones', 'id')->where('is_active', true)],
            'description' => 'nullable|array',
            'description.*' => 'string',
            'image_url' => 'nullable|url',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
            'surface_type' => ['nullable', Rule::in(['wood', 'synthetic', 'acrylic', 'concrete', 'grass', 'clay', 'hard', 'other'])],
            'capacity' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
        ]);
        $user = $request->user();
        if ($user) {
            abort_unless($user->canManageVenue((int) $data['venue_id']), 403, 'Unauthorized venue.');
        }

        $court = DB::transaction(function () use ($data) {
            $venue = Venue::findOrFail($data['venue_id']);
            if ($venue->organization_id) {
                $org = Organization::find($venue->organization_id);
                if ($org && $org->hasActiveSubscription()) {
                    app(EnforcePlanQuota::class)->execute($org->id, 'courts', 1);
                }
            }

            $court = Court::create($data)->load(['venue', 'sportZone']);
            Cache::increment('courts_version');

            return $court;
        });

        return response()->json($court, 201);
    }

    public function update(Request $request, Court $court)
    {
        $user = $request->user();
        if ($user) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        if (is_string($request->input('name'))) {
            $request->merge(['name' => ['vi' => $request->input('name'), 'en' => $request->input('name')]]);
        }
        if (is_string($request->input('description'))) {
            $request->merge(['description' => ['vi' => $request->input('description'), 'en' => $request->input('description')]]);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|array',
            'name.*' => 'string|max:120',
            'venue_id' => ['sometimes', 'required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'sport_zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sport_zones', 'id')->where('is_active', true)],
            'description' => 'nullable|array',
            'description.*' => 'string',
            'image_url' => 'nullable|url',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
            'surface_type' => ['sometimes', 'nullable', Rule::in(['wood', 'synthetic', 'acrylic', 'concrete', 'grass', 'clay', 'hard', 'other'])],
            'capacity' => 'sometimes|nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
        ]);

        if (isset($data['venue_id']) && $user) {
            abort_unless($user->canManageVenue((int) $data['venue_id']), 403, 'Unauthorized venue.');
        }

        DB::transaction(function () use ($court, $data) {
            if (isset($data['venue_id']) && (int) $data['venue_id'] !== (int) $court->venue_id) {
                $newVenue = Venue::findOrFail($data['venue_id']);
                if ($newVenue->organization_id) {
                    $org = Organization::find($newVenue->organization_id);
                    if ($org && $org->hasActiveSubscription()) {
                        app(EnforcePlanQuota::class)->execute($org->id, 'courts', 1);
                    }
                }
            }

            $court->update($data);
            abort_if($court->is_active && ! $court->venue()->where('is_active', true)->exists(), 422, 'Không thể mở sân tại địa điểm đang ngừng hoạt động.');
            Cache::increment('courts_version');
        });

        return $court->fresh(['venue', 'sportZone']);
    }

    public function destroy(Court $court)
    {
        $user = request()->user();
        if ($user) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        $court->update(['is_active' => false]);
        Cache::increment('courts_version');

        return ['message' => 'Đã ngừng mở sân.'];
    }
}

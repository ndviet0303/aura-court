<?php

namespace App\Http\Controllers\Court;

use App\Domain\Subscription\Actions\EnforcePlanQuota;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Domain\Venue\Rules\CourtInformationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Court\StoreCourtRequest;
use App\Http\Requests\Court\UpdateCourtRequest;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Venue;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Pricing\PriceCoverageService;
use App\Services\Reservation\CourtPriceRuleService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourtController extends Controller
{
    public function index(Request $request)
    {
        // Database cache counters must exist before mutations can increment them.
        Cache::add('courts_version', 1, now()->addYears(10));
        Cache::add('venues_version', 1, now()->addYears(10));
        $courtsVersion = Cache::get('courts_version', 1);
        $venuesVersion = Cache::get('venues_version', 1);
        // v3: entries cached before D9/D11 carry name, slug and image_url instead of code and images.
        $cacheKey = "courts:v3:c{$courtsVersion}:v{$venuesVersion}:".md5(json_encode($request->only(['sport_type', 'venue_id'])));
        $courts = Cache::remember($cacheKey, 300, function () use ($request) {
            $courtsQuery = Court::where('status', Court::STATUS_ACTIVE)
                ->whereHas('venue', fn ($query) => $query->where('status', Venue::STATUS_ACTIVE)->whereDoesntHave('activeSuspension'))
                ->when($request->query('sport_type'), function ($q, $sportType) {
                    $q->where('sport_type', $sportType);
                })
                ->when($request->query('venue_id'), function ($q, $venueId) {
                    $q->where('venue_id', $venueId);
                })
                ->with(['venue.organization'])
                ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('is_published', true)])
                ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->where('is_published', true)], 'rating')
                ->orderBy('id')
                ->get();

            $eligibility = app(VerifyEffectiveBookingEligibility::class);
            $features = app(FeatureFlagService::class);
            $venueEligibilityCache = [];
            $filteredCourts = [];
            $priceRules = app(CourtPriceRuleService::class);
            $pricedCourtIds = $priceRules->pricedCourtIds($courtsQuery);
            // BR-41, BR-47: one batch coverage check for the whole list; a court with any unpriced service time is hidden.
            $pricedCourtIds = array_diff_key($pricedCourtIds, array_flip(app(PriceCoverageService::class)->uncoveredCourtIds(array_keys($pricedCourtIds), now())));
            $fromPrices = $priceRules->fromPrices($courtsQuery);

            foreach ($courtsQuery as $court) {
                $venueId = (int) $court->venue_id;
                if (! isset($venueEligibilityCache[$venueId])) {
                    $venueEligibilityCache[$venueId] = $court->venue
                        ? $eligibility->evaluateVenue($court->venue)['eligible']
                        : false;
                }

                if ($venueEligibilityCache[$venueId] && isset($pricedCourtIds[$court->id])) {
                    $courtArray = $court->toArray();
                    // The organization row carries tax and KYC fields that public readers must not see
                    unset($courtArray['venue']['organization']);
                    $organizationId = (int) ($courtArray['venue']['organization_id'] ?? 0);
                    $courtArray['venue']['payment_model'] = $features->enabled('direct_owner_payments', $organizationId)
                        ? 'direct_owner_v1'
                        : 'legacy_wallet_v1';
                    $courtArray['from_price'] = $fromPrices[$court->id] ?? null;
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
        $court->loadMissing(['venue'])
            ->loadCount(['reviews as reviews_count' => fn ($query) => $query->where('is_published', true)])
            ->loadAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->where('is_published', true)], 'rating');
        $organizationId = (int) ($court->venue?->organization_id ?? 0);
        $court->venue?->setAttribute(
            'payment_model',
            app(FeatureFlagService::class)->enabled('direct_owner_payments', $organizationId)
                ? 'direct_owner_v1'
                : 'legacy_wallet_v1'
        );
        $court->venue?->unsetRelation('organization');
        $court->setAttribute('from_price', app(CourtPriceRuleService::class)->fromPrices([$court])[$court->id] ?? null);

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

        // Open stops and the venue suspension drive the admin status badges and the resume buttons.
        $query = Court::with(['venue.activeSuspension', 'operationalStops' => fn ($q) => $q->open()])
            ->when($scopedVenueIds !== null, fn ($q) => $q->whereIn('venue_id', $scopedVenueIds))
            ->when($request->query('sport_type'), fn ($q, $sportType) => $q->where('sport_type', $sportType))
            ->orderBy('id');

        return $query->get();
    }

    public function store(StoreCourtRequest $request)
    {
        $data = CourtInformationRules::toAttributes($request->validated());

        $court = self::uniqueCode(fn () => DB::transaction(function () use ($data) {
            // Locked, so a closure of the venue cannot slip in before the court exists.
            $venue = Venue::query()->lockForUpdate()->findOrFail($data['venue_id']);
            if ($venue->isClosed()) {
                throw ValidationException::withMessages(['venue_id' => 'Không thể thêm sân vào cơ sở đã ngừng kinh doanh.']);
            }
            if ($venue->organization_id) {
                $org = Organization::find($venue->organization_id);
                if ($org && $org->hasActiveSubscription()) {
                    app(EnforcePlanQuota::class)->execute($org->id, 'courts', 1);
                }
            }

            // D3: a new court starts inactive, also at an inactive venue, and opens only through OpenCourt.
            $court = Court::create([...$data, 'status' => Court::STATUS_INACTIVE])->load(['venue']);
            Cache::increment('courts_version');

            return $court;
        }));

        return response()->json($court, 201);
    }

    public function update(UpdateCourtRequest $request, Court $court)
    {
        // D10: venue and sport were only checked against the stored values; they never change here.
        $data = CourtInformationRules::toAttributes(Arr::except($request->validated(), ['venue_id', 'sport_type']));

        self::uniqueCode(fn () => DB::transaction(function () use ($court, $data) {
            $court->update($data);
            Cache::increment('courts_version');
        }));

        return $court->fresh(['venue']);
    }

    /** A concurrent save of the same code passes validation twice; the unique index refuses the second as a 422. */
    private static function uniqueCode(Closure $save): mixed
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Mã sân đã có ở một sân khác của cơ sở này.']);
        }
    }
}

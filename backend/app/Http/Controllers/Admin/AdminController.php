<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Contracts\Storage\FileStorageServiceInterface;
use App\Domain\Admin\Actions\ReviewPayment;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\UploadImageRequest;
use App\Models\Court;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\Venue;
use App\Services\Infrastructure\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly PaymentRepositoryInterface $payments,
        private readonly AuditLogger $audits,
        private readonly FileStorageServiceInterface $fileStorage,
        private readonly ReviewPayment $reviewPaymentAction,
    ) {}

    public function venues(Request $request)
    {
        $version = Cache::get('venues_version', 1);
        $user = $request->user();
        $isAdminRoute = $request->is('api/admin/venues');
        if ($isAdminRoute && $user) {
            abort_unless($user->organizationMembers()->where('status', 'active')->whereIn('role', [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_STAFF,
            ])->exists(), 403, 'Unauthorized venue management.');

            $authorizedVenueIds = $user->getAuthorizedVenueIds();
            $venues = Venue::query()
                ->whereIn('id', $authorizedVenueIds)
                ->withCount(['courts' => fn ($query) => $query->where('status', Court::STATUS_ACTIVE)])
                ->with([
                    'courts' => fn ($query) => $query->where('status', Court::STATUS_ACTIVE)->select(['id', 'venue_id', 'sport_type']),
                    'activeSuspension',
                ])
                ->orderBy('id')
                ->get();

            return response()->json($venues);
        }

        $cacheKey = "venues:v{$version}:".($request->query('sport_type') ?? 'all');
        $venues = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->venues->getActiveVenuesWithCourts($request->query('sport_type'))->toArray();
        });

        if (! $isAdminRoute) {
            $eligibility = app(VerifyEffectiveBookingEligibility::class);
            $venues = collect($venues)
                ->filter(fn (array $venue) => $eligibility->evaluateVenue((int) $venue['id'])['eligible'])
                ->values();
        }

        return response()->json($venues);
    }

    public function showVenue(string $slug)
    {
        $venue = $this->venues->findBySlug($slug);
        abort_unless($venue, 404);
        abort_unless(app(VerifyEffectiveBookingEligibility::class)->evaluateVenue($venue)['eligible'], 404);

        return $venue->unsetRelation('organization');
    }

    public function payments(Request $request)
    {
        return $this->payments->paginateManualReview(30);
    }

    public function reviewPayment(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'status' => 'required|in:paid,rejected',
            'note' => 'nullable|string|max:1000',
        ]);

        $updated = $this->reviewPaymentAction->execute($payment, $data, $request->user());
        $this->audits->record($request, 'payment.reviewed', $updated, ['status' => $data['status']]);

        return $updated;
    }

    public function upload(UploadImageRequest $request)
    {
        $result = $this->fileStorage->store($request->file('image'), 'courts');

        return response()->json($result);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Contracts\Storage\FileStorageServiceInterface;
use App\Domain\Admin\Actions\CreateVenue;
use App\Domain\Admin\Actions\ManageCourtBlocks;
use App\Domain\Admin\Actions\ReviewPayment;
use App\Domain\Admin\Actions\UpdateVenue;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\Venue;
use App\Services\Infrastructure\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly CourtBlockRepositoryInterface $courtBlocks,
        private readonly PaymentRepositoryInterface $payments,
        private readonly AuditLogger $audits,
        private readonly FileStorageServiceInterface $fileStorage,
        private readonly CreateVenue $createVenueAction,
        private readonly UpdateVenue $updateVenueAction,
        private readonly ManageCourtBlocks $manageCourtBlocksAction,
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
                ->with(['courts' => fn ($query) => $query->where('status', Court::STATUS_ACTIVE)->select(['id', 'venue_id', 'sport_type'])])
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

    public function storeVenue(Request $request)
    {
        $data = $this->validatedVenue($request);
        $user = $request->user();
        if ($user) {
            $orgId = $request->input('organization_id')
                ?? $user->organizations()->where('organizations.status', 'active')->first()?->id;
            abort_unless($orgId && $user->hasRoleInOrganization($orgId, [OrganizationMember::ROLE_ORGANIZATION_OWNER]), 403, 'Unauthorized organization.');
            $organization = Organization::find($orgId);
            abort_unless($organization?->isApprovedPartner(), 403, 'Câu lạc bộ cần được phê duyệt đối tác trước khi tạo cơ sở.');
            $data['organization_id'] = $orgId;
            $data['status'] = 'draft';
            $data['is_active'] = false;
        }

        $venue = $this->createVenueAction->execute($data);
        Cache::increment('venues_version');
        $this->audits->record($request, 'venue.created', $venue);

        return response()->json($venue, 201);
    }

    public function updateVenue(Request $request, Venue $venue)
    {
        $user = $request->user();
        abort_unless($user->canManageVenue($venue), 403, 'Unauthorized venue.');

        if ($request->boolean('is_active') && ! $venue->is_active && ! in_array($venue->status, ['published', 'active'], true)) {
            abort(409, 'Cơ sở phải hoàn tất điều kiện và quy trình duyệt trước khi mở công khai.');
        }

        if ($request->hasAny(['name', 'address', 'city', 'district', 'ward', 'phone', 'email', 'description', 'images', 'google_maps_url', 'latitude', 'longitude', 'amenities'])) {
            $data = $this->validatedVenue($request);
            $this->updateVenueAction->execute($venue, $data);
        }
        if ($request->has('is_active')) {
            $this->updateVenueAction->execute($venue, $request->validate(['is_active' => 'boolean']));
        }
        Cache::increment('venues_version');
        $this->audits->record($request, 'venue.updated', $venue, ['fields' => array_keys($request->all())]);

        return $venue->fresh(['courts' => fn ($query) => $query->where('is_active', true)->select(['id', 'venue_id', 'sport_type'])]);
    }

    public function destroyVenue(Venue $venue)
    {
        $user = request()->user();
        abort_unless($user->canManageVenue($venue), 403, 'Unauthorized venue.');

        $this->updateVenueAction->execute($venue, ['is_active' => false]);
        Cache::increment('venues_version');
        $this->audits->record(request(), 'venue.deactivated', $venue);

        return ['message' => 'Đã ngừng hoạt động địa điểm.'];
    }

    public function showVenue(string $slug)
    {
        $venue = $this->venues->findBySlug($slug);
        abort_unless($venue, 404);
        abort_unless(app(VerifyEffectiveBookingEligibility::class)->evaluateVenue($venue)['eligible'], 404);

        return $venue->unsetRelation('organization');
    }

    private function validatedVenue(Request $request): array
    {
        $rawName = $request->input('name');
        if (is_string($rawName)) {
            $request->merge(['name' => ['vi' => $rawName, 'en' => $rawName]]);
        }

        $isPatch = $request->isMethod('patch');
        $data = $request->validate([
            'name' => ($isPatch ? 'sometimes|' : '').'required|array',
            'name.*' => 'string|max:120',
            'address' => ($isPatch ? 'sometimes|' : '').'required|string|max:255',
            'city' => 'sometimes|nullable|string|max:100',
            'district' => 'sometimes|nullable|string|max:100',
            'ward' => 'sometimes|nullable|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
            'email' => 'sometimes|nullable|email|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'images' => 'sometimes|nullable|array',
            'images.*' => 'url|max:2048',
            'google_maps_url' => ($isPatch ? 'sometimes|' : '').'nullable|url|max:2048',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
            'is_active' => 'boolean',
        ]);

        if (isset($data['google_maps_url']) && ! $this->isGoogleMapsUrl($data['google_maps_url'])) {
            throw ValidationException::withMessages(['google_maps_url' => 'Chỉ chấp nhận liên kết Google Maps HTTPS hợp lệ.']);
        }

        return $data;
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

    public function blocks(Request $request, Court $court)
    {
        $user = $request->user();
        abort_unless($user->canOperateVenue($court->venue_id), 403, 'Unauthorized court venue.');

        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        $block = $this->manageCourtBlocksAction->createBlock($court, $data);
        $this->audits->record($request, 'court_block.created', $block, ['court_id' => $court->id]);

        return response()->json($block, 201);
    }

    public function blocksIndex(Court $court)
    {
        $user = request()->user();
        abort_unless($user->canOperateVenue($court->venue_id), 403, 'Unauthorized court venue.');

        return $this->courtBlocks->paginateForCourt($court, 50);
    }

    public function destroyBlock(Court $court, CourtBlock $block)
    {
        $user = request()->user();
        abort_unless($user->canOperateVenue($court->venue_id), 403, 'Unauthorized court venue.');

        $this->manageCourtBlocksAction->deleteBlock($court, $block);
        $this->audits->record(request(), 'court_block.deleted', $block, ['court_id' => $court->id]);

        return ['message' => 'Đã mở khóa lịch sân.'];
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

    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,webp,avif,gif|max:10240',
        ]);

        $result = $this->fileStorage->store($request->file('image'), 'courts');

        return response()->json($result);
    }
}

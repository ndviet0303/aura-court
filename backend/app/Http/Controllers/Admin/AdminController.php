<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Contracts\Storage\FileStorageServiceInterface;
use App\Domain\Admin\Actions\CreateVenue;
use App\Domain\Admin\Actions\ManageCourtBlocks;
use App\Domain\Admin\Actions\ManageCourtPrices;
use App\Domain\Admin\Actions\ReviewPayment;
use App\Domain\Admin\Actions\UpdateVenue;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\Payment;
use App\Models\PlatformPaymentAttempt;
use App\Models\PosOrder;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Venue;
use App\Services\Infrastructure\AuditLogger;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
        private readonly ManageCourtPrices $manageCourtPricesAction,
        private readonly ManageCourtBlocks $manageCourtBlocksAction,
        private readonly ReviewPayment $reviewPaymentAction,
    ) {}

    public function venues(Request $request)
    {
        $version = Cache::get('venues_version', 1);
        $user = $request->user();
        $isAdminRoute = $request->is('api/admin/venues');
        if ($isAdminRoute && $user && ! $user->isPlatformAdmin()) {
            abort_unless($user->organizationMembers()->where('status', 'active')->whereIn('role', [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_VENUE_MANAGER,
                OrganizationMember::ROLE_STAFF,
            ])->exists(), 403, 'Unauthorized venue management.');

            $authorizedVenueIds = $user->getAuthorizedVenueIds() ?? [];
            $venues = Venue::query()
                ->whereIn('id', $authorizedVenueIds)
                ->withCount(['courts' => fn ($query) => $query->where('is_active', true)])
                ->with(['sportZones' => fn ($query) => $query->where('is_active', true)->withCount('courts')])
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
        if ($user && ! $user->isPlatformAdmin()) {
            $orgId = $request->input('organization_id')
                ?? $user->organizations()->where('organizations.status', 'active')->first()?->id;
            abort_unless($orgId && $user->hasRoleInOrganization($orgId, [OrganizationMember::ROLE_ORGANIZATION_OWNER, OrganizationMember::ROLE_VENUE_MANAGER]), 403, 'Unauthorized organization.');
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
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($venue), 403, 'Unauthorized venue.');
        }

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

        return $venue->fresh('sportZones');
    }

    public function destroyVenue(Venue $venue)
    {
        $user = request()->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($venue), 403, 'Unauthorized venue.');
        }

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

        return $venue;
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

    public function pricesIndex(Request $request, Court $court)
    {
        $user = $request->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        return response()->json($court->priceSlots()->orderBy('weekday')->orderBy('starts_at')->get());
    }

    public function prices(Request $request, Court $court)
    {
        $user = $request->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        $data = $request->validate([
            'weekday' => 'required|integer|min:1|max:7',
            'starts_at' => 'required|date_format:H:i',
            'ends_at' => 'required|date_format:H:i',
            'price' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $price = $this->manageCourtPricesAction->createOrUpdatePrice($court, $data);
        $this->audits->record($request, 'price.updated', $price, ['court_id' => $court->id]);

        return response()->json($price, 201);
    }

    public function bulkPrices(Request $request, Court $court)
    {
        $user = $request->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        $data = $request->validate([
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'integer|min:1|max:7',
            'start_hour' => 'required|integer|min:0|max:23',
            'end_hour' => 'required|integer|gt:start_hour|max:24',
            'price' => 'required|integer|min:0',
        ]);

        $result = $this->manageCourtPricesAction->bulkUpdate($court, $data);
        $this->audits->record($request, 'price.bulk_updated', null, [
            'court_id' => $court->id,
            'weekdays' => $data['weekdays'],
            'start_hour' => $data['start_hour'],
            'end_hour' => $data['end_hour'],
            'price' => $data['price'],
            'count' => $result['count'],
        ]);

        return response()->json([
            'message' => 'Đã cập nhật khung giá hàng loạt.',
            'slots' => $result['slots'],
        ]);
    }

    public function blocks(Request $request, Court $court)
    {
        $user = $request->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        $block = $this->manageCourtBlocksAction->createBlock($court, $data);
        $this->audits->record($request, 'court_block.created', $block, ['court_id' => $court->id]);

        return response()->json($block, 201);
    }

    public function updatePrice(Request $request, Court $court, CourtPriceSlot $price)
    {
        $user = $request->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        $data = $request->validate([
            'weekday' => 'sometimes|integer|min:1|max:7',
            'starts_at' => 'sometimes|date_format:H:i',
            'ends_at' => 'sometimes|date_format:H:i',
            'price' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $updated = $this->manageCourtPricesAction->updatePrice($court, $price, $data);
        $this->audits->record($request, 'price.updated', $updated, ['court_id' => $court->id, 'fields' => array_keys($data)]);

        return $updated;
    }

    public function destroyPrice(Court $court, CourtPriceSlot $price)
    {
        $user = request()->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        $this->manageCourtPricesAction->deletePrice($court, $price);
        $this->audits->record(request(), 'price.deleted', $price, ['court_id' => $court->id]);

        return ['message' => 'Đã xóa khung giá.'];
    }

    public function blocksIndex(Court $court)
    {
        $user = request()->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

        return $this->courtBlocks->paginateForCourt($court, 50);
    }

    public function destroyBlock(Court $court, CourtBlock $block)
    {
        $user = request()->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($user->canManageVenue($court->venue_id), 403, 'Unauthorized court venue.');
        }

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

    public function report(Request $request)
    {
        $data = $this->buildReportDataset($request);

        $from = $data['from'];
        $to = $data['to'];
        $timezone = $data['timezone'];
        $scopedVenueIds = $data['scoped_venue_ids'];
        $canonicalBookings = $data['canonical_bookings'];

        // A V1 mirror slot is 'legacy' if its booking was inactive at backfill; V1 kept the slots of these statuses booked.
        $allConfirmedSlots = ReservationSlot::withoutGlobalScope('tenant_isolation')
            ->leftJoin('legacy_booking_mappings as lbm', 'lbm.reservation_id', '=', 'reservation_slots.reservation_id')
            ->where(fn ($q) => $q->where('reservation_slots.status', 'confirmed')
                ->orWhere(fn ($legacy) => $legacy->where('reservation_slots.status', 'legacy')->whereIn('lbm.legacy_status', ['no_show', 'completed', 'in_use'])))
            ->whereBetween('reservation_slots.starts_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->when($scopedVenueIds !== null, fn ($q) => $q->whereHas('court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds)))
            ->get(['reservation_slots.*']);

        $capacity = 0;
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()) as $day) {
            $capacity += CourtPriceSlot::query()
                ->where('is_active', true)
                ->where('weekday', $day->dayOfWeekIso)
                ->when($scopedVenueIds !== null, fn ($q) => $q->whereHas('court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds)))
                ->count();
        }

        $byHour = $allConfirmedSlots
            ->groupBy(fn ($slot) => $slot->starts_at->setTimezone($timezone)->format('H:00'))
            ->map(fn ($items, $hour) => ['hour' => $hour, 'bookings' => $items->count()])
            ->values();

        $byCourt = $canonicalBookings
            ->groupBy('court_id')
            ->map(function ($items, $courtId) {
                $court = $items->first()->court;

                return [
                    'court_id' => (int) $courtId,
                    'bookings' => $items->count(),
                    'revenue' => (int) $items->sum('total_amount'),
                    'court' => $court ? ['id' => $court->id, 'name' => $court->name] : null,
                ];
            })
            ->values();

        if ($byCourt->isEmpty() && $allConfirmedSlots->isNotEmpty()) {
            $byCourt = $allConfirmedSlots
                ->groupBy('court_id')
                ->map(function ($slots, $courtId) {
                    $court = Court::find($courtId);

                    return [
                        'court_id' => (int) $courtId,
                        'bookings' => $slots->count(),
                        'revenue' => (int) $slots->sum('unit_price'),
                        'court' => $court ? ['id' => $court->id, 'name' => $court->name] : null,
                    ];
                })
                ->values();
        }

        $pendingRefunds = (int) Refund::where('status', 'pending')
            ->whereBetween('created_at', [$from, $to])
            ->when($scopedVenueIds !== null, fn ($q) => $q->whereHas('booking.court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds)))
            ->sum('amount');
        $pendingRefunds += (int) OwnerRefundObligation::where('status', 'requested')
            ->whereBetween('created_at', [$from, $to])
            ->when($scopedVenueIds !== null, function ($q) use ($scopedVenueIds) {
                $q->where(function ($sub) use ($scopedVenueIds) {
                    $sub->whereIn('venue_id', $scopedVenueIds)
                        ->orWhereHas('reservation.court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds))
                        ->orWhereHas('booking.court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds));
                });
            })
            ->sum('amount');

        $canonicalCount = $canonicalBookings->count();

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'bookings' => $canonicalCount,
            'confirmed' => $canonicalCount,
            'revenue' => $data['owner_revenue'],
            'owner_revenue' => $data['owner_revenue'],
            'booking_revenue' => $data['booking_revenue'],
            'pos_revenue' => $data['pos_revenue'],
            'owner_refunds' => $data['owner_refunds'],
            'net_owner_revenue' => $data['net_owner_revenue'],
            'platform_fees' => $data['platform_fees'],
            'pendingRefunds' => $pendingRefunds,
            'occupancyRate' => $capacity > 0 ? round($allConfirmedSlots->count() / $capacity * 100, 1) : 0,
            'byHour' => $byHour,
            'byCourt' => $byCourt,
        ];
    }

    public function reportCsv(Request $request)
    {
        $data = $this->buildReportDataset($request);

        $csvRows = collect();

        // 1. Bookings: reservations, a V1 mirror shown with its V1 code
        foreach ($data['canonical_bookings'] as $reservation) {
            $court = $reservation->court;
            $venueOrCourt = $court?->name ?: ($court?->venue?->name ?: 'N/A');
            $customer = $reservation->user?->email ?: ($reservation->user?->name ?: 'N/A');

            $csvRows->push([
                'code' => (string) ($reservation->legacy_code ?? $reservation->code),
                'source_type' => 'booking',
                'customer' => $customer,
                'venue_or_court' => $venueOrCourt,
                'receiver_type' => 'owner',
                'status' => (string) ($reservation->status === 'legacy' ? $reservation->legacy_status : $reservation->status),
                'gross_amount' => (int) $reservation->total_amount,
                'refund_amount' => 0,
                'net_amount' => (int) $reservation->total_amount,
                'created_at' => $reservation->created_at?->toIso8601String(),
            ]);
        }

        // 2. POS orders
        foreach ($data['pos_orders'] as $pos) {
            $code = $pos->payment_reference ?: (data_get($pos->metadata, 'code') ?: ('POS-'.$pos->id));
            $customer = $pos->customer?->email ?: ($pos->customer?->name ?: (data_get($pos->metadata, 'customer_name') ?: 'Guest'));
            $venueOrCourt = $pos->venue?->name ?: ($pos->shift?->venue?->name ?: ($pos->reservation?->court?->name ?: 'N/A'));

            $csvRows->push([
                'code' => (string) $code,
                'source_type' => 'pos_order',
                'customer' => $customer,
                'venue_or_court' => $venueOrCourt,
                'receiver_type' => 'owner',
                'status' => (string) $pos->status,
                'gross_amount' => (int) $pos->total_amount,
                'refund_amount' => 0,
                'net_amount' => (int) $pos->total_amount,
                'created_at' => $pos->created_at?->toIso8601String(),
            ]);
        }

        // 3. Owner refunds
        foreach ($data['owner_refunds_collection'] as $ref) {
            $code = $ref->reference_code ?: ('REF-'.$ref->id);
            $customer = $ref->payer?->email ?: ($ref->payer?->name ?: 'N/A');
            $venueOrCourt = $ref->venue?->name ?: ($ref->reservation?->court?->venue?->name ?: ($ref->reservation?->court?->name ?: ($ref->booking?->court?->name ?: 'N/A')));

            $csvRows->push([
                'code' => (string) $code,
                'source_type' => 'owner_refund',
                'customer' => $customer,
                'venue_or_court' => $venueOrCourt,
                'receiver_type' => 'owner',
                'status' => (string) $ref->status,
                'gross_amount' => 0,
                'refund_amount' => (int) $ref->amount,
                'net_amount' => -((int) $ref->amount),
                'created_at' => $ref->created_at?->toIso8601String(),
            ]);
        }

        // 4. Platform subscriptions (only if user is platform admin)
        if ($data['is_platform_admin']) {
            foreach ($data['platform_attempts'] as $pa) {
                $code = $pa->reference_code ?: ('SUB-'.$pa->id);
                $customer = $pa->organization?->name ?: ($pa->subscription?->organization?->name ?: 'Platform');

                $csvRows->push([
                    'code' => (string) $code,
                    'source_type' => 'platform_subscription',
                    'customer' => $customer,
                    'venue_or_court' => 'Platform',
                    'receiver_type' => 'platform',
                    'status' => (string) $pa->status,
                    'gross_amount' => (int) $pa->amount,
                    'refund_amount' => 0,
                    'net_amount' => (int) $pa->amount,
                    'created_at' => $pa->created_at?->toIso8601String(),
                ]);
            }
        }

        $csvRows = $csvRows->sortByDesc('created_at')->values();

        return response()->streamDownload(function () use ($csvRows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['code', 'source_type', 'customer', 'venue_or_court', 'receiver_type', 'status', 'gross_amount', 'refund_amount', 'net_amount', 'created_at']);
            foreach ($csvRows as $row) {
                fputcsv($output, [
                    $row['code'],
                    $row['source_type'],
                    $row['customer'],
                    $row['venue_or_court'],
                    $row['receiver_type'],
                    $row['status'],
                    $row['gross_amount'],
                    $row['refund_amount'],
                    $row['net_amount'],
                    $row['created_at'],
                ]);
            }
            fclose($output);
        }, 'booking-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function buildReportDataset(Request $request): array
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $timezone)->startOfDay()
            : now($timezone)->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $timezone)->endOfDay()
            : now($timezone)->endOfMonth();

        $user = $request->user();
        $scopedVenueIds = null;
        if ($user && ! $user->isPlatformAdmin()) {
            $authorizedVenueIds = $user->getAuthorizedVenueIds() ?? [];
            if ($request->filled('venue_id')) {
                $requestedVenueId = (int) $request->input('venue_id');
                abort_unless(in_array($requestedVenueId, $authorizedVenueIds, true), 403, 'Unauthorized venue report.');
                $scopedVenueIds = [$requestedVenueId];
            } else {
                $scopedVenueIds = $authorizedVenueIds;
            }
        } elseif ($request->filled('venue_id')) {
            $scopedVenueIds = [(int) $request->input('venue_id')];
        }

        // 1. Bookings are reservations. A 'legacy' mirror counts by its V1 status, any other mirror by its own: V1 writers keep it current, and a V1 no-show stays confirmed as in V2.
        $canonicalBookings = Reservation::withoutGlobalScope('tenant_isolation')
            ->with(['court.venue', 'user'])
            ->leftJoin('legacy_booking_mappings as lbm', 'lbm.reservation_id', '=', 'reservations.id')
            ->select(['reservations.*', 'lbm.legacy_code', 'lbm.legacy_status'])
            ->whereRaw("CASE WHEN reservations.status = 'legacy' THEN lbm.legacy_status ELSE reservations.status END = 'confirmed'")
            ->whereBetween('reservations.created_at', [$from, $to])
            ->when($scopedVenueIds !== null, fn ($q) => $q->whereHas('court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds)))
            ->get();

        // 2. Query POS Orders (status in ['completed', 'paid'])
        $posOrdersQuery = PosOrder::query()
            ->with(['customer', 'cashier', 'venue', 'shift.venue', 'reservation.court.venue'])
            ->whereIn('status', ['completed', 'paid'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('completed_at', [$from, $to]);
            });

        if ($scopedVenueIds !== null) {
            $posOrdersQuery->where(function ($q) use ($scopedVenueIds) {
                $q->whereIn('venue_id', $scopedVenueIds)
                    ->orWhereHas('shift', fn ($s) => $s->whereIn('venue_id', $scopedVenueIds))
                    ->orWhereHas('reservation.court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds));
            });
        }

        $posOrders = $posOrdersQuery->get();

        // 3. Query Owner Refund Obligations (status in ['approved', 'reported', 'owner_reported', 'acknowledged'])
        $ownerRefundsQuery = OwnerRefundObligation::query()
            ->with(['payer', 'reservation.court.venue', 'booking.court.venue', 'venue'])
            ->whereIn('status', ['approved', 'reported', 'owner_reported', 'acknowledged'])
            ->whereBetween('created_at', [$from, $to]);

        if ($scopedVenueIds !== null) {
            $ownerRefundsQuery->where(function ($q) use ($scopedVenueIds) {
                $q->whereIn('venue_id', $scopedVenueIds)
                    ->orWhereHas('reservation.court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds))
                    ->orWhereHas('booking.court', fn ($c) => $c->whereIn('venue_id', $scopedVenueIds));
            });
        }

        $ownerRefundsCollection = $ownerRefundsQuery->get();

        // 4. Query Platform Subscriptions / Fees (only for platform admin)
        $isPlatformAdmin = $user && $user->isPlatformAdmin();
        $platformFees = 0;
        $platformAttempts = collect();

        if ($isPlatformAdmin) {
            $platformAttemptsQuery = PlatformPaymentAttempt::query()
                ->with(['organization', 'subscription.organization'])
                ->where('status', 'succeeded')
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('created_at', [$from, $to])
                        ->orWhereBetween('paid_at', [$from, $to]);
                });

            if ($scopedVenueIds !== null) {
                $scopedOrgIds = Venue::whereIn('id', $scopedVenueIds)->pluck('organization_id')->filter()->unique()->all();
                $platformAttemptsQuery->whereIn('organization_id', $scopedOrgIds);
            }

            $platformAttempts = $platformAttemptsQuery->get();
            $platformFees = (int) $platformAttempts->sum('amount');
        }

        $bookingRevenue = (int) $canonicalBookings->sum('total_amount');
        $posRevenue = (int) $posOrders->sum('total_amount');
        $ownerRevenue = $bookingRevenue + $posRevenue;
        $ownerRefunds = (int) $ownerRefundsCollection->sum('amount');
        $netOwnerRevenue = $ownerRevenue - $ownerRefunds;

        return [
            'timezone' => $timezone,
            'from' => $from,
            'to' => $to,
            'user' => $user,
            'is_platform_admin' => $isPlatformAdmin,
            'scoped_venue_ids' => $scopedVenueIds,
            'canonical_bookings' => $canonicalBookings,
            'pos_orders' => $posOrders,
            'owner_refunds_collection' => $ownerRefundsCollection,
            'platform_attempts' => $platformAttempts,
            'booking_revenue' => $bookingRevenue,
            'pos_revenue' => $posRevenue,
            'owner_revenue' => $ownerRevenue,
            'owner_refunds' => $ownerRefunds,
            'net_owner_revenue' => $netOwnerRevenue,
            'platform_fees' => $platformFees,
        ];
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

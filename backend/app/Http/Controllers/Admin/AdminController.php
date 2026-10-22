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
use App\Models\OwnerRefundObligation;
use App\Models\Payment;
use App\Models\PlatformPaymentAttempt;
use App\Models\PosOrder;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Venue;
use App\Services\Infrastructure\AuditLogger;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\Carbon;
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

        $capacity = app(VenueOperatingCalendar::class)->slotCapacity(
            Court::query()->where('status', Court::STATUS_ACTIVE)->when($scopedVenueIds !== null, fn ($q) => $q->whereIn('venue_id', $scopedVenueIds))->get(),
            $from,
            $to,
        );

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
                    'court' => $court ? ['id' => $court->id, 'code' => $court->code] : null,
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
                        'court' => $court ? ['id' => $court->id, 'code' => $court->code] : null,
                    ];
                })
                ->values();
        }

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
            $venueOrCourt = $court?->code ?: ($court?->venue?->name ?: 'N/A');
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
            $venueOrCourt = $pos->venue?->name ?: ($pos->shift?->venue?->name ?: ($pos->reservation?->court?->code ?: 'N/A'));

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
            $venueOrCourt = $ref->venue?->name ?: ($ref->reservation?->court?->venue?->name ?: ($ref->reservation?->court?->code ?: ($ref->booking?->court?->code ?: 'N/A')));

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
            abort_unless($user->organizationMembers()->where('status', 'active')
                ->whereIn('role', [OrganizationMember::ROLE_ORGANIZATION_OWNER, OrganizationMember::ROLE_STAFF])->exists(), 403, 'Organization admin or staff access required.');
            $authorizedVenueIds = $user->getAuthorizedVenueIds();
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

    public function upload(UploadImageRequest $request)
    {
        $result = $this->fileStorage->store($request->file('image'), 'courts');

        return response()->json($result);
    }
}

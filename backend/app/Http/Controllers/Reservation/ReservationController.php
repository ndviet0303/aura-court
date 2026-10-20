<?php

namespace App\Http\Controllers\Reservation;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\TenantCustomer;
use App\Models\User;
use App\Services\Booking\OrdinaryCheckoutService;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Infrastructure\TenantContext;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly ReservationService $reservations,
        private readonly IdempotencyService $idempotency,
        private readonly OrdinaryCheckoutService $checkoutService,
    ) {}

    public function index(Request $request)
    {
        $organization = $this->organization($request);
        $query = Reservation::query()
            ->where('organization_id', $organization->id)
            ->with(['court.venue', 'quote', 'slots', 'entitlements'])
            ->latest()
            ->where(fn ($q) => $this->scopeVisibleReservations($q, $request, $organization));

        return $query->paginate(20);
    }

    public function show(Request $request, string $reservation)
    {
        $organization = $this->organization($request);
        $record = $this->findReservation($request, $organization, $reservation);

        // legacy_mapping lets a deep link to a V1 mirror show the original booking code and status.
        $legacyMapping = fn ($q) => $q->withoutGlobalScope('tenant_isolation')->select('id', 'reservation_id', 'legacy_code', 'legacy_status');

        return response()->json(['reservation' => $record->load(['court.venue', 'quote', 'slots', 'entitlements', 'legacyMapping' => $legacyMapping])]);
    }

    public function payment(
        Request $request,
        string $reservation,
        ReservationPaymentService $payments
    ) {
        $organization = $this->organization($request);
        $record = $this->findReservation($request, $organization, $reservation);
        abort_unless((int) $record->user_id === (int) $request->user()->id, 403);
        $attempt = $payments->latest($record);

        return response()->json([
            'reservation' => ['code' => $record->code, 'status' => $record->status],
            'payment_attempt' => $attempt ? $payments->instructions($attempt) : null,
        ]);
    }

    public function createPayment(
        Request $request,
        string $reservation,
        ReservationPaymentService $payments,
        FeatureFlagService $features
    ) {
        $organization = $this->organization($request);
        $record = $this->findReservation($request, $organization, $reservation);
        abort_unless((int) $record->user_id === (int) $request->user()->id, 403);
        $existing = $payments->latest($record);
        if ($existing && ($existing->status === 'paid' || ($existing->status === 'pending' && $existing->expires_at?->isFuture()))) {
            return response()->json([
                'reservation' => ['code' => $record->code, 'status' => $record->status],
                'payment_attempt' => $payments->instructions($existing),
            ]);
        }
        $features->assertEnabled('direct_owner_payments', $record->organization_id);
        $attempt = $payments->initiate($record, $request->user());
        $record->refresh();

        return response()->json([
            'reservation' => ['code' => $record->code, 'status' => $record->status],
            'payment_attempt' => $payments->instructions($attempt),
        ], 201);
    }

    public function quote(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'court_id' => 'required|integer',
            'slots' => 'required|array|min:1|max:24',
            'slots.*' => 'required',
            'coupon_code' => 'nullable|string|max:40',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $quote = $this->quotes->create($request->user()->id, $organization->id, (int) $data['court_id'], $data['slots'], $data['coupon_code'] ?? null);

            return response()->json(['quote' => $quote], 201);
        });
    }

    public function store(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'quote_id' => 'nullable|integer',
            'quote_token' => 'nullable|string|max:80',
            'court_id' => 'required_without_all:quote_id,quote_token|integer',
            'slots' => 'required_without_all:quote_id,quote_token|array|min:1|max:24',
            'slots.*' => 'required',
            'customer_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:40',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $user = $request->user();
            $actor = null;
            if (! empty($data['customer_id'])) {
                abort_unless(
                    $user->hasRoleInOrganization($organization->id, ['organization_owner', 'staff']),
                    403,
                    'Only staff can perform walk-in bookings for other customers.'
                );
                $customer = User::findOrFail((int) $data['customer_id']);
                abort_if($customer->isRestricted(), 403, 'Tài khoản khách hàng đang bị khóa hoặc hạn chế.');
                abort_unless(
                    $organization->hasMember($customer->id) || TenantCustomer::where('organization_id', $organization->id)->where('user_id', $customer->id)->exists(),
                    422,
                    'Customer does not belong to this organization.'
                );
                $actor = $user;
                $user = $customer;
            } else {
                if (! $user->hasVerifiedEmail()) {
                    abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/auth/email/verification-notification.');
                }
            }

            if ($request->boolean('checkout')) {
                $reservation = $this->checkoutService->checkout($user, $organization->id, $data, $actor);
            } else {
                $reservation = $this->reservations->hold($user, $organization->id, $data, $actor);
            }

            return response()->json(['reservation' => $reservation], 201);
        });
    }

    public function checkout(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'quote_id' => 'nullable|integer',
            'quote_token' => 'nullable|string|max:80',
            'court_id' => 'required_without_all:quote_id,quote_token|integer',
            'slots' => 'required_without_all:quote_id,quote_token|array|min:1|max:24',
            'slots.*' => 'required',
            'customer_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:40',
            'loyalty_points' => 'nullable|integer|min:1',
            'loyalty_policy_version' => 'nullable|integer',
        ]);
        if (isset($data['court_id'])) {
            // Outside the idempotent transaction, so a request-time subscription expiry commits even though checkout is rejected.
            app(VerifyEffectiveBookingEligibility::class)->assertEligible((int) $data['court_id'], publicFacing: true);
        }

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $user = $request->user();
            $actor = null;
            if (! empty($data['customer_id'])) {
                abort_unless(
                    $user->hasRoleInOrganization($organization->id, ['organization_owner', 'staff']),
                    403,
                    'Only staff can perform walk-in bookings for other customers.'
                );
                $customer = User::findOrFail((int) $data['customer_id']);
                abort_if($customer->isRestricted(), 403, 'Tài khoản khách hàng đang bị khóa hoặc hạn chế.');
                abort_unless(
                    $organization->hasMember($customer->id) || TenantCustomer::where('organization_id', $organization->id)->where('user_id', $customer->id)->exists(),
                    422,
                    'Customer does not belong to this organization.'
                );
                $actor = $user;
                $user = $customer;
            } else {
                if (! $user->hasVerifiedEmail()) {
                    abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/auth/email/verification-notification.');
                }
            }
            $reservation = $this->checkoutService->checkout($user, $organization->id, $data, $actor);

            return response()->json(['reservation' => $reservation], 201);
        });
    }

    public function confirm(Request $request, string $reservation)
    {
        $organization = $this->organization($request);
        $record = $this->findReservation($request, $organization, $reservation);

        return $this->idempotency->run($request, function () use ($record) {
            return response()->json(['reservation' => $this->reservations->confirm($record)]);
        });
    }

    public function pricingCalendar(Request $request)
    {
        $organization = $this->organization($request);
        // BR-49: pricing configuration belongs to the owner; a court_id must not open another tenant's rules.
        abort_unless($this->isOrganizationOperator($request, $organization), 403);
        $query = PriceRule::query()->where('organization_id', $organization->id)->where('is_active', true)->with(['venue', 'court']);
        if ($request->filled('court_id')) {
            $query->where(function ($rules) use ($request) {
                $rules->whereNull('court_id')->orWhere('court_id', (int) $request->integer('court_id'));
            });
        }

        return response()->json(['rules' => $query->orderBy('type')->orderBy('priority', 'desc')->get()]);
    }

    /**
     * The caller's own reservation in any organization, or one at a venue of the context
     * organization they operate. Binds the tenant context to the record's organization.
     */
    private function findReservation(Request $request, Organization $organization, string $identifier): Reservation
    {
        $query = Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->where(fn ($q) => $this->scopeVisibleReservations($q, $request, $organization));
        if (ctype_digit($identifier)) {
            $query->whereKey((int) $identifier);
        } else {
            $query->where('code', $identifier);
        }
        $record = $query->firstOrFail();
        $recordOrganization = $record->organization;
        abort_unless($recordOrganization instanceof Organization, 404);
        app(TenantContext::class)->setOrganization($recordOrganization);

        return $record;
    }

    /**
     * BR-21: the owner manages every booking of the organization, staff only those at their assigned venues.
     *
     * @param  Builder<Reservation>  $query
     */
    private function scopeVisibleReservations(Builder $query, Request $request, Organization $organization): void
    {
        $venueIds = $request->user()->getAuthorizedVenueIds($organization->id);
        $query->where('user_id', $request->user()->id)
            ->orWhere(fn ($q) => $q->where('organization_id', $organization->id)
                ->whereHas('court', fn ($court) => $court->whereIn('venue_id', $venueIds)));
    }
}

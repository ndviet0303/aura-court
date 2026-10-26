<?php

namespace App\Http\Controllers\Reservation;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Exceptions\VoucherNoLongerApplies;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TenantCustomer;
use App\Models\User;
use App\Models\VoucherUse;
use App\Services\Booking\OrdinaryCheckoutService;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\ReservationIntake;
use App\Services\Reservation\ReservationService;
use App\Services\Reservation\VisibleReservations;
use App\Services\Social\ResalePriceCap;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    use ResolvesOrganization;

    /** Court bookings take no coupon and no points (design D21, D28): naming one is refused, not ignored. */
    private const NO_BENEFITS = ['coupon_code' => 'prohibited', 'loyalty_points' => 'prohibited'];

    private const NO_BENEFIT_MESSAGES = [
        'coupon_code.prohibited' => 'Mã coupon không còn áp dụng cho đặt sân.',
        'loyalty_points.prohibited' => 'Đặt sân không còn dùng điểm tích lũy.',
    ];

    /** A booking names a public program or a private code, never both (design D17). */
    private const VOUCHER = [
        'voucher_program_id' => 'nullable|integer|prohibits:voucher_code',
        'voucher_code' => 'nullable|string|max:64',
    ];

    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly ReservationService $reservations,
        private readonly IdempotencyService $idempotency,
        private readonly OrdinaryCheckoutService $checkoutService,
        private readonly VisibleReservations $visible,
        private readonly ReservationIntake $intake,
    ) {}

    public function index(Request $request, ResalePriceCap $caps)
    {
        $organization = $this->organization($request);
        $query = Reservation::query()
            ->where('organization_id', $organization->id)
            ->with(['court.venue', 'quote', 'slots', 'entitlements'])
            ->latest()
            ->where(fn ($q) => $this->visible->scope($q, $request->user(), $organization));

        // The sell tab offers each slot up to its max_resale_price (design D23).
        return $caps->onPage($query->paginate(20));
    }

    public function show(Request $request, string $reservation, ReservationPaymentService $payments)
    {
        $organization = $this->organization($request);
        $record = $this->visible->find($request->user(), $organization, $reservation);

        // legacy_mapping lets a deep link to a V1 mirror show the original booking code and status.
        $legacyMapping = fn ($q) => $q->withoutGlobalScope('tenant_isolation')->select('id', 'reservation_id', 'legacy_code', 'legacy_status');
        // Check-in, check-out and no-show times only; staff notes stay with the venue.
        $attendance = fn ($q) => $q->select('id', 'reservation_id', 'status', 'checked_in_at', 'checked_out_at', 'no_show_at');
        // Incident handling: status and source window only; contact channel and notes stay with the venue.
        $affectedCase = fn ($q) => $q->select(array_map(fn (string $column): string => 'affected_reservation_cases.'.$column, ['id', 'reservation_id', 'source_type', 'source_starts_at', 'source_ends_at', 'status', 'handled_at']));
        $record->load(['court.venue', 'quote', 'slots', 'entitlements', 'legacyMapping' => $legacyMapping, 'attendance' => $attendance, 'latestAffectedCase' => $affectedCase]);
        // The voucher the booking holds or used, read from its use row (design D17); a released use shows nothing.
        $use = $record->voucherUse()->whereIn('status', [VoucherUse::STATUS_HELD, VoucherUse::STATUS_USED])
            ->with(['program:id,name', 'voucherCode:id,code'])->first();
        $voucher = $use === null ? null : ['name' => $use->program?->name, 'code' => $use->voucherCode?->code, 'discount' => $use->discount];

        return response()->json(['reservation' => [...$record->toArray(), 'voucher' => $voucher, 'payment' => $payments->result($record)]]);
    }

    public function payment(
        Request $request,
        string $reservation,
        ReservationPaymentService $payments
    ) {
        $organization = $this->organization($request);
        $record = $this->visible->find($request->user(), $organization, $reservation);
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
        $record = $this->visible->find($request->user(), $organization, $reservation);
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
        $this->refuseSlotList($request);
        $data = $request->validate([
            'court_id' => 'required|integer',
            'starts_at' => 'required|string|max:40',
            'ends_at' => 'required|string|max:40',
            ...self::NO_BENEFITS,
        ], self::NO_BENEFIT_MESSAGES);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $quote = $this->quotes->create($request->user()->id, $organization->id, (int) $data['court_id'], $data['starts_at'], $data['ends_at']);

            return response()->json(['quote' => $quote], 201);
        });
    }

    public function store(Request $request)
    {
        $organization = $this->organization($request);
        $this->refuseSlotList($request);
        $data = $request->validate([
            'quote_id' => 'nullable|integer',
            'quote_token' => 'nullable|string|max:80',
            'court_id' => 'required_without_all:quote_id,quote_token|integer',
            'starts_at' => 'required_without_all:quote_id,quote_token|string|max:40',
            'ends_at' => 'required_without_all:quote_id,quote_token|string|max:40',
            'customer_id' => 'nullable|integer',
            'expected_total' => 'required|integer|min:0',
            ...self::VOUCHER,
            'venue_credit_amount' => 'prohibited',
            ...self::NO_BENEFITS,
        ], self::NO_BENEFIT_MESSAGES);

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
        $this->refuseSlotList($request);
        $data = $request->validate([
            'quote_id' => 'nullable|integer',
            'quote_token' => 'nullable|string|max:80',
            'court_id' => 'required_without_all:quote_id,quote_token|integer',
            'starts_at' => 'required_without_all:quote_id,quote_token|string|max:40',
            'ends_at' => 'required_without_all:quote_id,quote_token|string|max:40',
            'customer_id' => 'nullable|integer',
            'expected_total' => 'required|integer|min:0',
            ...self::VOUCHER,
            'venue_credit_amount' => 'nullable|integer|min:0|prohibits:customer_id',
            ...self::NO_BENEFITS,
        ], self::NO_BENEFIT_MESSAGES);
        if (isset($data['court_id'])) {
            // Early public-facing rejection; checkout rechecks under the court and venue locks.
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
        $record = $this->visible->find($request->user(), $organization, $reservation);

        return $this->idempotency->run($request, function () use ($record) {
            try {
                return response()->json(['reservation' => $this->reservations->confirm($record)]);
            } catch (VoucherNoLongerApplies $refused) {
                // D12: answered inside the idempotent transaction, so the booking's release commits with the 422.
                return $refused->render();
            }
        });
    }

    /** A booking is one interval now (design D14): a list of start times is refused, not converted. */
    private function refuseSlotList(Request $request): void
    {
        if ($request->has('slots')) {
            $this->intake->refuseBookingTime();
        }
    }
}

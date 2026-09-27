<?php

namespace App\Http\Controllers\Booking;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RecurringBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\Reservation;
use App\Services\Booking\BookingService;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepo,
        private readonly BookingService $bookings,
        private readonly PaymentService $payments,
        private readonly WalletPaymentService $walletPayments,
    ) {}

    public function index(Request $request)
    {
        return $this->bookingRepo->paginateUserBookings($request->user()->id, 20);
    }

    public function store(StoreBookingRequest $request)
    {
        $data = $request->toData();
        $booking = $this->bookings->createFromData($data);

        return response()->json(['booking' => $booking], 201);
    }

    public function recurring(RecurringBookingRequest $request)
    {
        $data = $request->validated();
        $bookings = $this->bookings->createRecurring($request->user()->id, $data['court_id'], $data['slots'], $data['weeks']);

        return response()->json(['bookings' => collect($bookings)->map(fn (Booking $booking) => ['booking' => $booking])->values()], 201);
    }

    public function show(Request $request, Booking $booking)
    {
        $user = $request->user();
        $isOwnerOrStaff = $booking->court?->venue?->organization_id
            && $user->hasRoleInOrganization($booking->court->venue->organization_id, ['organization_owner', 'venue_manager', 'staff']);

        abort_unless($booking->user_id === $user->id || $user->isAdmin() || $isOwnerOrStaff, 403);

        return $booking->load(['court.venue', 'slots', 'payments', 'refunds']);
    }

    public function paymentStatus(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        if ($booking->payment_model === 'direct_owner_v1') {
            $reservation = $this->directReservation($booking);
            $attempt = app(OwnerPaymentAttemptService::class)->latestForPurpose('booking', $reservation->code);

            return [
                'status' => $booking->status,
                'payment' => null,
                'payment_model' => 'direct_owner_v1',
                'payment_attempt' => $attempt ? app(ReservationPaymentService::class)->instructions($attempt) : null,
            ];
        }

        return ['status' => $booking->status, 'payment' => $booking->payments()->latest()->first()];
    }

    public function payment(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $booking->loadMissing('court.venue');

        if ($booking->payment_model === 'direct_owner_v1') {
            $reservation = $this->directReservation($booking);
            $attempt = app(OwnerPaymentAttemptService::class)->latestForPurpose('booking', $reservation->code);

            return [
                'booking' => $booking->load(['court.venue', 'slots', 'coupon']),
                'status' => $booking->status,
                'payment_model' => 'direct_owner_v1',
                'payment_attempt' => $attempt ? app(ReservationPaymentService::class)->instructions($attempt) : null,
            ];
        }

        $orgId = $booking->court?->venue?->organization_id;
        $wallet = $this->walletPayments->getOrCreateWallet($request->user()->id, $orgId);

        return [
            'booking' => $booking->load(['court.venue', 'slots', 'coupon']),
            'wallet' => $wallet,
            'status' => $booking->status,
        ];
    }

    public function createPayment(
        Request $request,
        Booking $booking,
        ReservationPaymentService $payments,
        FeatureFlagService $features
    ): JsonResponse {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);
        abort_unless($booking->payment_model === 'direct_owner_v1', 409, 'This booking does not use direct owner payment.');
        $organizationId = (int) $booking->court?->venue?->organization_id;
        $reservation = $this->directReservation($booking);
        $attempts = app(OwnerPaymentAttemptService::class);
        $existing = $attempts->latestForPurpose('booking', $reservation->code);
        if ($existing && ($existing->status === 'paid' || ($existing->status === 'pending' && $existing->expires_at?->isFuture()))) {
            return response()->json([
                'booking' => ['code' => $booking->code, 'status' => $booking->status],
                'payment_attempt' => $payments->instructions($existing),
            ]);
        }
        $features->assertEnabled('direct_owner_payments', $organizationId);
        $attempt = $payments->initiate($reservation, $request->user());

        return response()->json([
            'booking' => ['code' => $booking->code, 'status' => $booking->status],
            'payment_attempt' => $payments->instructions($attempt),
        ], 201);
    }

    public function applyCoupon(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_if($booking->payment_model === 'direct_owner_v1', 409, 'The accepted direct-payment quote cannot be changed after the hold is created.');
        $data = $request->validate([
            'coupon_code' => 'nullable|string|max:40',
        ]);

        $updated = $this->bookings->applyCoupon($booking, $data['coupon_code'] ?? null);

        return response()->json([
            'booking' => $updated->load(['court.venue', 'slots', 'coupon']),
            'status' => $updated->status,
        ]);
    }

    public function calendar(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $booking->load(['court.venue', 'slots']);
        $escape = static fn (string $value): string => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
        $events = $booking->slots->whereIn('status', ['held', 'confirmed'])->map(function ($slot) use ($booking, $escape) {
            $start = $slot->starts_at->utc()->format('Ymd\\THis\\Z');
            $end = $slot->ends_at->utc()->format('Ymd\\THis\\Z');
            $location = $booking->court->venue?->address ?: $booking->court->venue?->name ?: 'Aura Court';

            return implode("\r\n", [
                'BEGIN:VEVENT',
                'UID:'.$booking->code.'-'.$slot->id.'@auracourt',
                'DTSTAMP:'.now()->utc()->format('Ymd\\THis\\Z'),
                'DTSTART:'.$start,
                'DTEND:'.$end,
                'SUMMARY:'.$escape('Đặt sân '.$booking->court->name),
                'LOCATION:'.$escape($location),
                'DESCRIPTION:Booking '.$booking->code,
                'END:VEVENT',
            ]);
        })->implode("\r\n");
        $ics = implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Aura Court//Booking//VI', 'CALSCALE:GREGORIAN', $events, 'END:VCALENDAR', '']);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$booking->code.'.ics"',
        ]);
    }

    private function directReservation(Booking $booking): Reservation
    {
        $mapping = LegacyBookingMapping::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('legacy_booking_id', $booking->id)
            ->firstOrFail();

        return Reservation::query()->withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
    }
}

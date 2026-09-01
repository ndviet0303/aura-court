<?php

namespace App\Http\Controllers\Booking;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelSlotsRequest;
use App\Http\Requests\Booking\RecurringBookingRequest;
use App\Http\Requests\Booking\RescheduleBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\WalletPaymentService;
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
        $booking = $this->bookings->create($data->userId, $data->courtId, $data->slots, $data->couponCode);

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
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return $booking->load(['court.venue', 'slots', 'payments', 'refunds']);
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return $this->bookings->cancel($booking, $request->input('reason'));
    }

    public function cancelSlots(CancelSlotsRequest $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $data = $request->validated();

        return $this->bookings->cancelSlots($booking, $data['slot_ids'], $data['reason'] ?? null);
    }

    public function reschedule(RescheduleBookingRequest $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $data = $request->validated();
        $replacement = $this->bookings->reschedule($booking, $data['court_id'], $data['slots']);

        return response()->json(['booking' => $replacement], 201);
    }

    public function paymentStatus(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return ['status' => $booking->status, 'payment' => $booking->payments()->latest()->first()];
    }

    public function payment(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $booking->loadMissing('court.venue');
        $orgId = $booking->court?->venue?->organization_id;
        $wallet = $this->walletPayments->getOrCreateWallet($request->user()->id, $orgId);

        return [
            'booking' => $booking->load(['court.venue', 'slots', 'coupon']),
            'wallet' => $wallet,
            'status' => $booking->status,
        ];
    }

    public function applyCoupon(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings, private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        return $request->user()->bookings()->with(['court', 'slots', 'refunds'])->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['court_id' => 'required|integer|exists:courts,id', 'slots' => 'required|array|min:1|max:12', 'slots.*' => 'date']);
        $booking = $this->bookings->create($request->user()->id, $data['court_id'], $data['slots']);

        return response()->json(['booking' => $booking, 'payment' => $this->payments->qrPayload($booking)], 201);
    }

    public function recurring(Request $request)
    {
        $data = $request->validate(['court_id' => 'required|integer|exists:courts,id', 'slots' => 'required|array|min:1|max:12', 'slots.*' => 'date', 'weeks' => 'required|integer|min:2|max:12']);
        $bookings = $this->bookings->createRecurring($request->user()->id, $data['court_id'], $data['slots'], $data['weeks']);

        return response()->json(['bookings' => collect($bookings)->map(fn (Booking $booking) => ['booking' => $booking, 'payment' => $this->payments->qrPayload($booking)])->values()], 201);
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return $booking->load(['court', 'slots', 'payments', 'refunds']);
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return $this->bookings->cancel($booking, $request->input('reason'));
    }

    public function cancelSlots(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $data = $request->validate(['slot_ids' => 'required|array|min:1', 'slot_ids.*' => 'integer', 'reason' => 'nullable|string|max:255']);

        return $this->bookings->cancelSlots($booking, $data['slot_ids'], $data['reason'] ?? null);
    }

    public function reschedule(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $data = $request->validate(['court_id' => 'required|integer|exists:courts,id', 'slots' => 'required|array|min:1|max:12', 'slots.*' => 'date']);
        $replacement = $this->bookings->reschedule($booking, $data['court_id'], $data['slots']);

        return response()->json(['booking' => $replacement, 'payment' => $this->payments->qrPayload($replacement)], 201);
    }

    public function paymentStatus(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        if ($booking->status === 'pending_payment' && $booking->payment_expires_at?->isPast()) {
            $this->bookings->expirePending();
            $booking->refresh();
        }

        return ['status' => $booking->status, 'payment' => $booking->payments()->latest()->first(), 'expiresAt' => $booking->payment_expires_at];
    }

    public function payment(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return ['booking' => $booking->load(['court', 'slots']), 'payment' => $this->payments->qrPayload($booking), 'status' => $booking->status];
    }
}

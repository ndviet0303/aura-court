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

    public function paymentStatus(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return ['status' => $booking->status, 'payment' => $booking->payments()->latest()->first(), 'expiresAt' => $booking->payment_expires_at];
    }
}

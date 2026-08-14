<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings, private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        return $request->user()->bookings()->with(['court.venue', 'slots', 'refunds', 'review'])->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['court_id' => 'required|integer|exists:courts,id', 'slots' => 'required|array|min:1|max:12', 'slots.*' => 'date', 'coupon_code' => 'nullable|string|max:40']);
        $booking = $this->bookings->create($request->user()->id, $data['court_id'], $data['slots'], $data['coupon_code'] ?? null);

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

        return $booking->load(['court.venue', 'slots', 'payments', 'refunds']);
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

        return ['booking' => $booking->load(['court.venue', 'slots']), 'payment' => $this->payments->qrPayload($booking), 'status' => $booking->status];
    }

    public function demoPay(Request $request, Booking $booking)
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless($booking->status === 'pending_payment', 422, 'Booking không ở trạng thái chờ thanh toán.');
        abort_if($booking->payment_expires_at?->isPast(), 422, 'Phiên thanh toán đã hết hạn.');

        return DB::transaction(function () use ($booking) {
            $payment = $booking->payments()->latest()->first();
            if ($payment instanceof Payment) {
                $payment->update([
                    'status' => 'paid',
                    'amount' => $booking->total_amount,
                    'payload' => array_merge($payment->payload ?? [], ['demo' => true, 'paid_at' => now()->toIso8601String()]),
                    'received_at' => now(),
                ]);
            } else {
                $booking->payments()->create([
                    'provider' => 'demo',
                    'external_transaction_id' => 'demo-'.$booking->code,
                    'amount' => $booking->total_amount,
                    'transfer_code' => $booking->code,
                    'status' => 'paid',
                    'payload' => ['demo' => true],
                    'received_at' => now(),
                ]);
            }
            $booking->update(['status' => 'confirmed']);
            $booking->slots()->where('status', 'held')->update(['status' => 'confirmed']);

            $this->payments->notifyConfirmed($booking->fresh(['user', 'court']));

            return ['status' => 'confirmed', 'booking' => $booking->fresh(['court.venue', 'slots', 'payments'])];
        });
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

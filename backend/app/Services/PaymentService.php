<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\AuraBookingNotification;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private readonly FinancialPostingService $financials) {}

    public function qrPayload(Booking $booking): array
    {
        $account = config('services.sepay.account');
        $bank = config('services.sepay.bank');
        $query = http_build_query(['acc' => $account, 'bank' => $bank, 'amount' => $booking->total_amount, 'des' => $booking->code]);

        return ['code' => $booking->code, 'amount' => $booking->total_amount, 'bank' => $bank, 'accountNumber' => $account, 'qrUrl' => "https://vietqr.app/img?{$query}", 'expiresAt' => $booking->payment_expires_at];
    }

    public function handleWebhook(array $data, string $raw): void
    {
        $externalId = (string) ($data['id'] ?? '');
        if ($externalId === '') {
            return;
        }

        $confirmedBooking = null;
        DB::transaction(function () use ($data, $raw, $externalId, &$confirmedBooking) {
            if (Payment::where('external_transaction_id', $externalId)->exists()) {
                return;
            }
            $code = trim((string) ($data['code'] ?? $data['content'] ?? ''));
            $booking = Booking::where('code', $code)->lockForUpdate()->first();
            $amount = (int) ($data['transferAmount'] ?? $data['amount'] ?? 0);
            $valid = $booking && ($data['transferType'] ?? 'in') === 'in' && $amount >= $booking->total_amount
                && $booking->status === 'pending_payment' && $booking->payment_expires_at?->isFuture();
            $payment = Payment::create([
                'booking_id' => $booking?->id,
                'external_transaction_id' => $externalId,
                'amount' => $amount,
                'transfer_code' => $code,
                'status' => $valid ? 'paid' : 'manual_review',
                'payload' => json_decode($raw, true),
                'received_at' => now(),
            ]);
            if ($valid) {
                $booking->update(['status' => 'confirmed']);
                $booking->slots()->where('status', 'held')->update(['status' => 'confirmed']);
                $payment->loadMissing('booking.court.venue');
                $this->financials->postLegacyBookingPayment($booking->fresh(['court.venue']), $payment);
                $confirmedBooking = $booking->fresh(['user', 'court']);
            }
        });
        if ($confirmedBooking) {
            $this->notifyConfirmed($confirmedBooking);
        }
    }

    public function notifyConfirmed(Booking $booking): void
    {
        $booking->loadMissing(['user', 'court']);
        $booking->user->notify(new AuraBookingNotification(
            'payment_confirmed',
            'Thanh toán đã được xác nhận',
            "Booking {$booking->code} tại {$booking->court->name} đã thanh toán thành công.",
            '/booking',
        ));
    }
}

<?php

namespace App\Domain\Payment\Actions;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\AuraBookingNotification;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Support\Facades\DB;

class ProcessQrPayment
{
    public function __construct(
        private readonly FinancialPostingService $financials,
    ) {}

    public function execute(array $data, string $raw): void
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
            $rawContent = trim((string) ($data['code'] ?? $data['content'] ?? ''));
            $prefix = (string) config('services.sepay.prefix', 'ORD');

            $booking = null;

            // 1. Extract numeric booking ID from "ORD 12345" or "ORD12345"
            if (preg_match('/^(?:' . preg_quote($prefix, '/') . '|ORD|AUR)\s*(\d{1,30})$/i', $rawContent, $m)) {
                $booking = Booking::where('id', (int) $m[1])->lockForUpdate()->first();
            }

            // 2. Direct match with booking->code
            if (!$booking) {
                $booking = Booking::where('code', $rawContent)->lockForUpdate()->first();
            }

            // 3. Strip prefix if present (e.g. "ORD BK123" -> "BK123")
            if (!$booking && $prefix !== '') {
                $stripped = trim(preg_replace('/^' . preg_quote($prefix, '/') . '\s*/i', '', $rawContent));
                if ($stripped !== '') {
                    $booking = Booking::where('code', $stripped)->lockForUpdate()->first();
                }
            }

            // 3. Fallback: search for active booking code contained within content
            if (!$booking && strlen($rawContent) >= 3) {
                $candidateCodes = Booking::where('status', 'pending_payment')
                    ->where('payment_expires_at', '>', now())
                    ->pluck('code');

                foreach ($candidateCodes as $cCode) {
                    if (stripos($rawContent, $cCode) !== false) {
                        $booking = Booking::where('code', $cCode)->lockForUpdate()->first();
                        break;
                    }
                }
            }

            $code = $booking ? $booking->code : $rawContent;
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

    private function notifyConfirmed(Booking $booking): void
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

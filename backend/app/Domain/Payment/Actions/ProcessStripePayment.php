<?php

namespace App\Domain\Payment\Actions;

use App\Models\Booking;
use App\Notifications\AuraBookingNotification;
use App\Services\FinancialPostingService;
use Illuminate\Support\Facades\DB;

class ProcessStripePayment
{
    public function __construct(
        private readonly FinancialPostingService $financials,
    ) {}

    public function execute(Booking $booking, array $cardDetails = []): array
    {
        abort_unless($booking->status === 'pending_payment', 422, 'Booking không ở trạng thái chờ thanh toán.');
        abort_if($booking->payment_expires_at?->isPast(), 422, 'Phiên thanh toán đã hết hạn.');

        return DB::transaction(function () use ($booking, $cardDetails) {
            $payment = $booking->payments()->create([
                'provider' => 'stripe',
                'external_transaction_id' => 'ch_'.strtolower($booking->code).'_'.substr(md5(uniqid()), 0, 12),
                'amount' => $booking->total_amount,
                'transfer_code' => $booking->code,
                'status' => 'paid',
                'payload' => array_merge($cardDetails, [
                    'brand' => $cardDetails['brand'] ?? 'visa',
                    'last4' => isset($cardDetails['card_number']) ? substr(preg_replace('/\D/', '', (string) $cardDetails['card_number']), -4) : '4242',
                    'paid_at' => now()->toIso8601String(),
                ]),
                'received_at' => now(),
            ]);

            $booking->update(['status' => 'confirmed']);
            $booking->slots()->where('status', 'held')->update(['status' => 'confirmed']);
            $payment->loadMissing('booking.court.venue');
            $this->financials->postLegacyBookingPayment($booking->fresh(['court.venue']), $payment);

            $confirmedBooking = $booking->fresh(['user', 'court']);
            $this->notifyConfirmed($confirmedBooking);

            return ['status' => 'confirmed', 'booking' => $booking->fresh(['court.venue', 'slots', 'payments'])];
        });
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

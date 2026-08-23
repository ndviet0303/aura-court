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
        $account = config('services.sepay.account') ?: '0987654321';
        $bank = config('services.sepay.bank') ?: 'VCB';
        $accountName = config('services.sepay.account_name') ?: 'AURA COURT';
        $query = http_build_query(['acc' => $account, 'bank' => $bank, 'amount' => $booking->total_amount, 'des' => $booking->code]);

        return [
            'provider' => 'sepay',
            'code' => $booking->code,
            'amount' => $booking->total_amount,
            'bank' => $bank,
            'accountNumber' => $account,
            'accountName' => $accountName,
            'qrUrl' => "https://vietqr.app/img?{$query}",
            'expiresAt' => $booking->payment_expires_at,
        ];
    }

    public function stripePayload(Booking $booking): array
    {
        $key = config('services.stripe.key') ?: 'pk_test_auracourt';

        return [
            'provider' => 'stripe',
            'publishableKey' => $key,
            'amount' => $booking->total_amount,
            'currency' => 'vnd',
            'clientSecret' => 'pi_'.strtolower($booking->code).'_secret_'.substr(md5($booking->code), 0, 16),
            'code' => $booking->code,
        ];
    }

    public function processStripePayment(Booking $booking, array $cardDetails = []): array
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

    public function handleStripeWebhook(array $data, string $raw, string $signature): void
    {
        $event = $data['type'] ?? '';
        if (in_array($event, ['payment_intent.succeeded', 'charge.succeeded', 'checkout.session.completed'])) {
            $obj = $data['data']['object'] ?? [];
            $code = $obj['metadata']['booking_code'] ?? $obj['description'] ?? '';
            $booking = Booking::where('code', $code)->first();
            if ($booking && $booking->status === 'pending_payment') {
                $this->processStripePayment($booking, ['stripe_event_id' => $data['id'] ?? null]);
            }
        }
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

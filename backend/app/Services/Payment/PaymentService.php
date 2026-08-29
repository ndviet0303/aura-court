<?php

namespace App\Services\Payment;

use App\Services\Finance\FinancialPostingService;

use App\Domain\Payment\Actions\ProcessQrPayment;
use App\Domain\Payment\Actions\ProcessStripePayment;
use App\Models\Booking;
use App\Notifications\AuraBookingNotification;

class PaymentService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly ProcessStripePayment $processStripePaymentAction,
        private readonly ProcessQrPayment $processQrPaymentAction,
    ) {}

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
        return $this->processStripePaymentAction->execute($booking, $cardDetails);
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
        $this->processQrPaymentAction->execute($data, $raw);
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

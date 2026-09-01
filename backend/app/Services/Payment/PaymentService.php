<?php

namespace App\Services\Payment;

use App\Domain\Payment\Actions\ProcessQrPayment;
use App\Models\Booking;
use App\Notifications\AuraBookingNotification;
use App\Services\Finance\FinancialPostingService;

class PaymentService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly ProcessQrPayment $processQrPaymentAction,
    ) {}

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

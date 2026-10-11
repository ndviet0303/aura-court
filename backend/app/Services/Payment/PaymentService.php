<?php

namespace App\Services\Payment;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Domain\Payment\Actions\ProcessQrPayment;
use App\Models\Booking;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly ProcessQrPayment $processQrPaymentAction,
        private readonly DurableNotificationServiceInterface $durableNotification,
    ) {}

    public function handleWebhook(array $data, string $raw): void
    {
        $this->processQrPaymentAction->execute($data, $raw);
    }

    public function notifyConfirmed(Booking $booking): void
    {
        $booking->loadMissing(['user', 'court']);
        try {
            $this->durableNotification->notifyBooking(
                $booking,
                'payment_confirmed',
                'Thanh toán đã được xác nhận',
                "Booking {$booking->code} tại {$booking->court->name} đã thanh toán thành công.",
                '/booking',
            );
        } catch (\Throwable $secondaryException) {
            Log::warning("Secondary payment confirmation notification dispatch encountered failure: {$secondaryException->getMessage()}", [
                'booking_code' => $booking->code,
                'exception' => $secondaryException,
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
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

        DB::transaction(function () use ($data, $raw, $externalId) {
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
            }
        });
    }
}

<?php

namespace App\Services\Payment;

use App\Models\FinanceException;
use App\Models\OwnerPaymentReceipt;

/** Records a direct-owner receipt that could not be allocated, once, with what the owner's lookup shows (design D8). */
class OwnerPaymentExceptionRecorder
{
    public function record(OwnerPaymentReceipt $receipt, string $exceptionCode): void
    {
        $type = 'owner_payment_'.$exceptionCode;
        $reference = 'owner-receipt:'.$receipt->id;
        $existing = FinanceException::withoutGlobalScopes()
            ->where('organization_id', $receipt->organization_id)
            ->where('type', $type)
            ->where('reference', $reference)
            ->first();
        if ($existing) {
            return;
        }

        FinanceException::create([
            'organization_id' => $receipt->organization_id,
            'type' => $type,
            'severity' => in_array($exceptionCode, ['foreign_receiver', 'transfer_after_expiry', 'excess_amount', 'processing_exhausted'], true) ? 'high' : 'medium',
            'status' => 'open',
            'amount' => (int) $receipt->amount,
            'currency' => $receipt->currency,
            'reference' => $reference,
            'description' => 'Authenticated direct-owner receipt requires reconciliation ('.$exceptionCode.').',
            'links' => [
                'owner_payment_receipt_id' => $receipt->id,
                'payment_attempt_id' => $receipt->payment_attempt_id,
                'receiving_connection_id' => $receipt->receiving_connection_id,
            ],
            'metadata' => [
                'provider' => $receipt->provider,
                'credential_version' => (int) $receipt->credential_version,
                'exception_code' => $exceptionCode,
                // The receipt carries the purpose only of the request its code matched; an unmatched code keeps them null.
                'purpose' => $receipt->purpose,
                'purpose_id' => $receipt->purpose_id,
                'payment_code' => $receipt->payment_code,
                'event_at' => $receipt->event_at?->toIso8601String(),
                'bank_code' => $receipt->connection?->bank_code,
                'account_last_four' => $receipt->connection?->account_last_four,
            ],
            'detected_at' => now(),
            'due_at' => now()->addMinutes(15),
        ]);
    }
}

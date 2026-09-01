<?php

namespace App\Domain\Payment\Actions;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Support\Facades\DB;

class ProcessQrPayment
{
    public function __construct(
        private readonly WalletPaymentService $walletPayments,
    ) {}

    public function execute(array $data, string $raw): void
    {
        $externalId = (string) ($data['id'] ?? '');
        if ($externalId === '') {
            return;
        }

        DB::transaction(function () use ($data, $raw, $externalId) {
            if (Payment::where('external_transaction_id', $externalId)->exists()) {
                return;
            }

            $rawContent = trim((string) ($data['code'] ?? $data['content'] ?? ''));
            $amount = (int) ($data['transferAmount'] ?? $data['amount'] ?? 0);
            $type = $data['transferType'] ?? 'in';

            if ($type !== 'in' || $amount <= 0) {
                return;
            }

            // Extract user from content like "ORD NAP 1", "NAP 0901234567"
            $user = null;
            if (preg_match('/(?:NAP|WALLET)\s*([0-9a-zA-Z\-_]+)/i', $rawContent, $m)) {
                $identifier = trim($m[1]);
                if (is_numeric($identifier)) {
                    $user = User::find((int) $identifier) ?? User::where('phone', $identifier)->first();
                } else {
                    $user = User::where('phone', $identifier)->orWhere('email', $identifier)->first();
                }
            }

            $payment = Payment::create([
                'booking_id' => null,
                'external_transaction_id' => $externalId,
                'amount' => $amount,
                'transfer_code' => $rawContent,
                'status' => $user ? 'paid' : 'manual_review',
                'payload' => json_decode($raw, true),
                'received_at' => now(),
            ]);

            if ($user) {
                $wallet = $this->walletPayments->getOrCreateWallet($user->id);
                $this->walletPayments->credit(
                    $wallet,
                    $amount,
                    'wallet_deposit',
                    $payment->id,
                    "Deposit via VietQR: {$externalId}"
                );
            }
        });
    }
}

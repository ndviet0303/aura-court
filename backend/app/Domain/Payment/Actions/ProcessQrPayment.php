<?php

namespace App\Domain\Payment\Actions;

use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PosOrder;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\WalletPaymentService;
use App\Services\POS\PosService;
use Illuminate\Support\Facades\DB;

class ProcessQrPayment
{
    public function __construct(
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
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

            // Check if transfer code is for a pending POS Order (e.g. POS-1-66AB... or ORD POS 123)
            if (preg_match('/(POS-[0-9a-zA-Z\-_]+)/i', $rawContent, $mPos)) {
                $posRef = trim($mPos[1]);
                $posOrder = PosOrder::where('payment_reference', $posRef)
                    ->where('status', 'pending_payment')
                    ->first();
                if ($posOrder && $amount >= $posOrder->total_amount) {
                    app(PosService::class)->confirmQrPayment($posOrder, [
                        'external_transaction_id' => $externalId,
                        'amount' => $amount,
                        'source' => 'vietqr_webhook',
                    ]);
                    Payment::create([
                        'booking_id' => null,
                        'external_transaction_id' => $externalId,
                        'amount' => $amount,
                        'transfer_code' => $rawContent,
                        'status' => 'paid',
                        'payload' => json_decode($raw, true),
                        'received_at' => now(),
                    ]);

                    return;
                }
            }
            // Look for PaymentIntent first: wallet top-up memo ORD{intent id}, or legacy DEP-ABC12345-1
            $intent = null;
            if (preg_match('/\bORD\s*(\d{1,11})\b/i', $rawContent, $mOrd)) {
                $intent = PaymentIntent::whereKey((int) $mOrd[1])
                    ->whereJsonContains('metadata->type', 'wallet_deposit')
                    ->first();
            }
            if (! $intent && preg_match('/DEP-([0-9a-zA-Z\-_]+)/i', $rawContent, $mDep)) {
                $intent = PaymentIntent::where('reference', $mDep[0])
                    ->orWhere('idempotency_key', $mDep[0])
                    ->first();
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

            if (! $intent && $user) {
                $intent = PaymentIntent::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->where('amount', $amount)
                    ->whereJsonContains('metadata->type', 'wallet_deposit')
                    ->latest('id')
                    ->first();
            }

            if ($intent && ! $user) {
                $user = User::find($intent->user_id);
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

            if ($intent) {
                if ($intent->status === 'succeeded') {
                    return;
                }

                $wallet = $this->walletPayments->getOrCreateWallet($intent->user_id, $intent->organization_id);
                $this->walletPayments->credit(
                    $wallet,
                    $amount,
                    'wallet_deposit',
                    $payment->id,
                    "Deposit via VietQR: {$externalId}"
                );

                $intent->update([
                    'status' => 'succeeded',
                    'succeeded_at' => now(),
                    'provider_intent_id' => $externalId,
                ]);

                $this->financials->postWalletDeposit(
                    $intent->organization_id,
                    $wallet->id,
                    $amount,
                    "dep-{$intent->id}-{$payment->id}",
                    Payment::class,
                    $payment->id
                );
            } elseif ($user) {
                $wallet = $this->walletPayments->getOrCreateWallet($user->id);
                $this->walletPayments->credit(
                    $wallet,
                    $amount,
                    'wallet_deposit',
                    $payment->id,
                    "Deposit via VietQR: {$externalId}"
                );

                $this->financials->postWalletDeposit(
                    $wallet->organization_id,
                    $wallet->id,
                    $amount,
                    "deposit-{$payment->id}",
                    Payment::class,
                    $payment->id
                );
            }
        });
    }
}

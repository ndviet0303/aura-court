<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Package\Actions\ApplyPackagePayment;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Support\Facades\DB;

class ProcessQrPayment
{
    public function __construct(
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
        private readonly ApplyPackagePayment $packagePayments,
    ) {}

    public function execute(array $data, string $raw): void
    {
        $externalId = (string) ($data['id'] ?? '');
        if ($externalId === '') {
            return;
        }

        // D5: a package fee is never a wallet top-up, even with an unknown code or a memo that also names a wallet.
        if (preg_match('/\bPKG([A-Z0-9]{8})\b/i', ($data['content'] ?? '').' '.($data['code'] ?? ''), $package)) {
            // An outgoing transfer quoting the code, such as a manual refund, pays nothing.
            if (($data['transferType'] ?? 'in') === 'in') {
                $this->packagePayments->execute('PKG'.strtoupper($package[1]), $data, $raw);
            }

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

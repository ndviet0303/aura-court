<?php

namespace App\Services\Payment;

use App\Models\PaymentIntent;
use App\Models\ProviderEvent;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Support\Facades\DB;

class ProviderEventHandler
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly WalletPaymentService $walletPayments,
    ) {}

    public function handle(ProviderEvent $event): void
    {
        $status = strtoupper((string) $event->status);
        $amount = (int) $event->amount;

        $intent = null;
        if ($event->reference) {
            $intent = PaymentIntent::where('reference', $event->reference)
                ->orWhere('idempotency_key', $event->reference)
                ->first();
        }

        if (! $intent && $event->provider_payment_id) {
            $intent = PaymentIntent::where('provider_intent_id', $event->provider_payment_id)->first();
        }

        if ($intent) {
            if (in_array($status, ['PAID', 'SUCCEEDED', 'SUCCESS', 'COMPLETED'], true)) {
                if ($amount <= 0) {
                    return;
                }

                if ($intent->status === 'succeeded') {
                    return;
                }

                DB::transaction(function () use ($intent, $event, $amount) {
                    $lockedIntent = PaymentIntent::whereKey($intent->id)->lockForUpdate()->first();
                    if ($lockedIntent->status === 'succeeded') {
                        return;
                    }

                    $creditAmount = $amount > 0 ? $amount : (int) $lockedIntent->amount;
                    $wallet = $this->walletPayments->getOrCreateWallet($lockedIntent->user_id, $lockedIntent->organization_id);

                    $this->walletPayments->credit(
                        $wallet,
                        $creditAmount,
                        PaymentIntent::class,
                        $lockedIntent->id,
                        "Deposit via {$event->provider}: {$event->event_id}"
                    );

                    $lockedIntent->update([
                        'status' => 'succeeded',
                        'succeeded_at' => now(),
                        'provider_intent_id' => $event->provider_payment_id ?? $lockedIntent->provider_intent_id,
                    ]);

                    $this->financials->postProviderCollection($event, $lockedIntent->organization_id);
                    $this->financials->postWalletCredit(
                        $lockedIntent->organization_id,
                        $wallet->id,
                        $creditAmount,
                        "dep-{$lockedIntent->id}-{$event->event_id}"
                    );
                });
            } elseif (in_array($status, ['FAILED', 'CANCELLED', 'EXPIRED'], true)) {
                if ($intent->status === 'pending') {
                    $intent->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                    ]);
                }
            }
        } else {
            if (in_array($status, ['PAID', 'SUCCEEDED', 'SUCCESS', 'COMPLETED'], true) && $amount > 0) {
                $this->financials->postProviderCollection($event);
            }
        }
    }
}

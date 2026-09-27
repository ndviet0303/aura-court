<?php

namespace App\Services\Payment;

use App\Models\PaymentIntent;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformProviderReceipt;
use App\Models\ProviderEvent;
use App\Domain\Subscription\Actions\ConfirmSubscriptionPayment;
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

        $platformAttempt = null;
        if (($event->reference !== null && $event->reference !== '')
            || ($event->provider_payment_id !== null && $event->provider_payment_id !== '')) {
            $platformAttempt = PlatformPaymentAttempt::query()
                ->where('provider', $event->provider)
                ->where(function ($query) use ($event): void {
                    if ($event->reference !== null && $event->reference !== '') {
                        $query->orWhere('reference_code', $event->reference);
                    }
                    if ($event->provider_payment_id !== null && $event->provider_payment_id !== '') {
                        $query->orWhere('provider_ref', $event->provider_payment_id);
                    }
                })
                ->first();
        }

        if ($platformAttempt) {
            if (
                ! $event->verified_at
                || ! in_array($status, ['PAID', 'SUCCEEDED', 'SUCCESS', 'COMPLETED'], true)
                || $platformAttempt->status !== 'pending'
                || $event->amount === null
                || $amount !== (int) $platformAttempt->amount
                || strtoupper(trim($event->currency)) !== strtoupper(trim($platformAttempt->currency))
            ) {
                return;
            }

            $providerTransactionId = (string) ($event->provider_payment_id ?: $event->event_id);
            $receipt = DB::transaction(function () use ($platformAttempt, $event, $providerTransactionId, $amount): PlatformProviderReceipt {
                $lockedAttempt = PlatformPaymentAttempt::query()->lockForUpdate()->findOrFail($platformAttempt->id);
                $existing = PlatformProviderReceipt::query()
                    ->where('payment_attempt_id', $lockedAttempt->id)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }

                return PlatformProviderReceipt::create([
                    'payment_attempt_id' => $lockedAttempt->id,
                    'provider' => $event->provider,
                    'provider_transaction_id' => $providerTransactionId,
                    'amount' => $amount,
                    'currency' => strtoupper(trim($event->currency)),
                    'raw_payload' => $event->payload,
                    'verified_at' => $event->verified_at,
                ]);
            });

            app(ConfirmSubscriptionPayment::class)->execute(
                referenceCode: $platformAttempt->reference_code,
                providerTransactionId: $receipt->provider_transaction_id,
                amount: (int) $receipt->amount,
                currency: $receipt->currency,
                rawPayload: (array) $receipt->raw_payload,
                actorId: isset($platformAttempt->metadata['actor_id']) ? (int) $platformAttempt->metadata['actor_id'] : null,
            );

            return;
        }

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

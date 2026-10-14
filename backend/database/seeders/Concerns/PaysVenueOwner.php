<?php

namespace Database\Seeders\Concerns;

use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\SePayReceivingConnectionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeded payments take the live direct-owner path: an attempt on the venue's verified connection, an authenticated SePay
 * event on that connection, an allocated receipt and the owner.direct_receipt journal. Never a platform `payments` row.
 */
trait PaysVenueOwner
{
    /**
     * Records a paid owner attempt opened at $openedAt and settled by a bank transfer at $paidAt.
     * The receipt is allocated here rather than by processReceipt, whose purpose confirmers would re-run the live
     * side effects (confirm a held booking, complete an order) on records the seeder already wrote in their final state.
     */
    protected function payOwner(
        Venue $venue,
        ?User $payer,
        string $purpose,
        string $purposeId,
        int $amount,
        Carbon $openedAt,
        Carbon $paidAt,
        ?string $reference = null
    ): OwnerPaymentAttempt {
        $existing = OwnerPaymentAttempt::where('organization_id', $venue->organization_id)
            ->where('purpose', $purpose)
            ->where('purpose_id', $purposeId)
            ->where('user_id', $payer?->id)
            ->where('status', OwnerPaymentAttempt::STATUS_PAID)
            ->first();
        if ($existing) {
            return $existing;
        }

        $connection = $venue->currentReceivingConnection()->firstOrFail();
        $reference ??= 'AC'.Str::upper(Str::random(18));
        $attempt = $this->atClock($openedAt, fn () => app(OwnerPaymentAttemptService::class)->createAttempt(
            $connection,
            $payer,
            $purpose,
            $purposeId,
            $reference,
            $amount,
            'VND',
            $openedAt->copy()->addMinutes(15)->max($paidAt->copy()->addMinute()),
            "seed:{$purpose}:{$purposeId}:{$reference}",
        ));

        return $this->atClock($paidAt, function () use ($connection, $attempt, $amount, $paidAt, $reference): OwnerPaymentAttempt {
            $event = [
                'id' => random_int(10_000_000, 99_999_999),
                'gateway' => $connection->bank_code,
                'transactionDate' => $paidAt->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'accountNumber' => $connection->accountNumber(),
                'code' => $reference,
                'content' => $reference.' thanh toan',
                'transferType' => 'in',
                'transferAmount' => $amount,
                'referenceCode' => 'FT'.$paidAt->format('ymd').random_int(100000, 999999),
            ];
            $verified = app(SePayReceivingConnectionService::class)
                ->verifyInboundWebhook($connection, 'Apikey '.$connection->webhookSecret(), json_encode($event, JSON_THROW_ON_ERROR));
            $receipt = app(OwnerPaymentAttemptService::class)->receiveVerifiedEvent($connection, $verified);

            return DB::transaction(function () use ($connection, $attempt, $receipt, $paidAt): OwnerPaymentAttempt {
                OwnerPaymentAllocation::create([
                    'organization_id' => $attempt->organization_id,
                    'venue_id' => $attempt->venue_id,
                    'receiving_connection_id' => $connection->id,
                    'payment_attempt_id' => $attempt->id,
                    'payment_receipt_id' => $receipt->id,
                    'purpose' => $attempt->purpose,
                    'purpose_id' => $attempt->purpose_id,
                    'amount' => (int) $receipt->amount,
                    'currency' => $receipt->currency,
                    'credential_version' => (int) $receipt->credential_version,
                    'receiver_snapshot_ciphertext' => $attempt->receiver_snapshot_ciphertext,
                    'purpose_snapshot_ciphertext' => $attempt->purpose_snapshot_ciphertext,
                    'allocated_at' => $paidAt,
                ]);
                $receipt->forceFill([
                    'payment_attempt_id' => $attempt->id,
                    'purpose' => $attempt->purpose,
                    'purpose_id' => $attempt->purpose_id,
                    'purpose_snapshot_ciphertext' => $attempt->purpose_snapshot_ciphertext,
                    'status' => OwnerPaymentReceipt::STATUS_ALLOCATED,
                    'processing_attempts' => 1,
                    'processed_at' => $paidAt,
                    'next_attempt_at' => null,
                ])->save();
                $attempt->forceFill(['status' => OwnerPaymentAttempt::STATUS_PAID, 'paid_at' => $paidAt])->save();
                app(FinancialPostingService::class)->postDirectOwnerReceipt($receipt);

                return $attempt;
            });
        });
    }

    private function atClock(Carbon $at, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($at);
        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }
}

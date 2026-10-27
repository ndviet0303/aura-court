<?php

namespace App\Services\Payment;

use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\VenueCreditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationPaymentService
{
    public function __construct(private readonly OwnerPaymentAttemptService $attempts) {}

    /**
     * Create (or return the live) pending owner-payment attempt for a reservation-bound obligation.
     *
     * Defaults model a single 'booking' obligation keyed by the reservation code that expires
     * exactly at the reservation's payment deadline, so reopening payment never extends it.
     */
    public function initiate(Reservation $reservation, User $payer): OwnerPaymentAttempt
    {
        return DB::transaction(function () use ($reservation, $payer): OwnerPaymentAttempt {
            $venue = Venue::query()->whereKey($reservation->court()->value('venue_id'))->lockForUpdate()->firstOrFail();
            $connectionId = $venue->current_receiving_connection_id;
            abort_unless($connectionId, 409, 'This venue has no selected receiving account.');

            $connection = ReceivingConnection::query()->lockForUpdate()->findOrFail($connectionId);
            abort_unless((int) $connection->venue_id === (int) $venue->id
                && (int) $connection->organization_id === (int) $venue->organization_id, 409, 'The selected receiving account does not belong to this venue.');

            $purpose = 'booking';
            $purposeId = $reservation->code;

            // Receipt processing locks attempt then reservation; preserve the same order here.
            $latestAttempt = OwnerPaymentAttempt::query()
                ->where('organization_id', $reservation->organization_id)
                ->where('purpose', $purpose)
                ->where('purpose_id', $purposeId)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $lockedReservation = Reservation::query()
                ->withoutGlobalScope('tenant_isolation')
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedReservation->user_id === (int) $payer->id, 403);
            abort_unless($lockedReservation->payment_model === 'direct_owner_v1', 409, 'This reservation does not use direct owner payment.');

            if ($latestAttempt?->status === OwnerPaymentAttempt::STATUS_PAID) {
                return $latestAttempt;
            }
            if ($latestAttempt?->status === OwnerPaymentAttempt::STATUS_PENDING && $latestAttempt->expires_at?->isFuture()) {
                return $latestAttempt;
            }
            if ($latestAttempt?->status === OwnerPaymentAttempt::STATUS_PENDING) {
                $latestAttempt->forceFill(['status' => OwnerPaymentAttempt::STATUS_EXPIRED])->save();
            }

            // Venue credit reserved at checkout already pays its part, so the QR asks only for the rest (D5).
            $amount = (int) $lockedReservation->total_amount - app(VenueCreditService::class)->reservedFor($lockedReservation);
            abort_unless($lockedReservation->status === 'held', 409, 'This reservation is no longer awaiting payment.');
            abort_unless($lockedReservation->hold_expires_at?->isFuture(), 409, 'The reservation hold has expired.');
            abort_unless($amount > 0, 409, 'No bank payment is required for this reservation.');

            $expiresAt = Carbon::parse($lockedReservation->hold_expires_at);
            $reference = 'AC'.Str::upper(Str::random(18));

            return $this->attempts->createAttempt(
                $connection,
                $payer,
                $purpose,
                $purposeId,
                $reference,
                $amount,
                strtoupper((string) $lockedReservation->currency),
                $expiresAt,
                'reservation:'.$lockedReservation->id.':'.$purpose.':'.$reference,
            );
        });
    }

    public function latest(Reservation $reservation): ?OwnerPaymentAttempt
    {
        return OwnerPaymentAttempt::query()
            ->where('organization_id', $reservation->organization_id)
            ->where('purpose', 'booking')
            ->where('purpose_id', $reservation->code)
            ->latest('id')
            ->first();
    }

    /**
     * Payment result for the booking detail (design D10): the latest payment request and the latest transfer
     * received for it, without receiver details. Null when the booking never had a payment request.
     */
    public function result(Reservation $reservation): ?array
    {
        $attempt = $this->latest($reservation);
        if (! $attempt) {
            return null;
        }
        $receipt = OwnerPaymentReceipt::query()->where('payment_attempt_id', $attempt->id)->latest('id')->first();

        return [
            'status' => $this->attemptStatus($attempt),
            'amount' => (int) $attempt->amount,
            'currency' => $attempt->currency,
            'expires_at' => $attempt->expires_at->toIso8601String(),
            'paid_at' => $attempt->paid_at?->toIso8601String(),
            'receipt' => $receipt ? [
                'status' => $receipt->status,
                'amount' => (int) $receipt->amount,
                'currency' => $receipt->currency,
                'transferred_at' => $receipt->event_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function instructions(OwnerPaymentAttempt $attempt): array
    {
        $receiver = $attempt->receiverSnapshot();
        $status = $this->attemptStatus($attempt);
        $accountNumber = (string) $receiver['account_number'];

        return [
            'id' => (int) $attempt->id,
            'provider' => $attempt->provider,
            'reference' => $attempt->reference,
            'transfer_content' => $attempt->reference,
            'amount' => (int) $attempt->amount,
            'currency' => $attempt->currency,
            'status' => $status,
            'expires_at' => $attempt->expires_at->toIso8601String(),
            'paid_at' => $attempt->paid_at?->toIso8601String(),
            'receiver' => [
                'bank_code' => $receiver['bank_code'],
                'account_name' => $receiver['account_name'],
                'account_number' => $accountNumber,
            ],
            'qr_url' => VietQrImage::url((string) $receiver['bank_code'], $accountNumber, (string) $receiver['account_name'], (int) $attempt->amount, (string) $attempt->reference),
        ];
    }

    private function attemptStatus(OwnerPaymentAttempt $attempt): string
    {
        return $attempt->status === OwnerPaymentAttempt::STATUS_PENDING && $attempt->expires_at?->isPast()
            ? OwnerPaymentAttempt::STATUS_EXPIRED
            : $attempt->status;
    }
}

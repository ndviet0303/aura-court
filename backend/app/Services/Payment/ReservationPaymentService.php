<?php

namespace App\Services\Payment;

use App\Models\OwnerPaymentAttempt;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationPaymentService
{
    public function __construct(private readonly OwnerPaymentAttemptService $attempts) {}

    /**
     * Create (or return the live) pending owner-payment attempt for a reservation-bound obligation.
     *
     * Defaults model a single 'booking' obligation keyed by the reservation code with the
     * 15-minute-capped hold TTL. Recurring flows pass an explicit $purpose ('recurring_period'),
     * a period/series $purposeId, the aggregate $amountOverride, and the period-deadline
     * $expiresAtOverride (which bypasses the 15-minute cap).
     */
    public function initiate(
        Reservation $reservation,
        User $payer,
        string $purpose = 'booking',
        ?string $purposeId = null,
        ?int $amountOverride = null,
        ?CarbonInterface $expiresAtOverride = null,
    ): OwnerPaymentAttempt {
        return DB::transaction(function () use ($reservation, $payer, $purpose, $purposeId, $amountOverride, $expiresAtOverride): OwnerPaymentAttempt {
            $venue = Venue::query()->whereKey($reservation->court()->value('venue_id'))->lockForUpdate()->firstOrFail();
            $connectionId = $venue->current_receiving_connection_id;
            abort_unless($connectionId, 409, 'This venue has no selected receiving account.');

            $connection = ReceivingConnection::query()->lockForUpdate()->findOrFail($connectionId);
            abort_unless((int) $connection->venue_id === (int) $venue->id
                && (int) $connection->organization_id === (int) $venue->organization_id, 409, 'The selected receiving account does not belong to this venue.');

            $purpose = strtolower(trim($purpose));
            $purposeId ??= $reservation->code;

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

            $amount = $amountOverride ?? (int) $lockedReservation->total_amount;
            abort_unless($lockedReservation->status === 'held', 409, 'This reservation is no longer awaiting payment.');
            abort_unless($lockedReservation->hold_expires_at?->isFuture(), 409, 'The reservation hold has expired.');
            abort_unless($amount > 0, 409, 'No bank payment is required for this reservation.');

            if ($expiresAtOverride !== null) {
                $expiresAt = Carbon::parse($expiresAtOverride);
                abort_unless($expiresAt->isFuture(), 409, 'The payment deadline has already passed.');
            } else {
                $holdExpiresAt = Carbon::parse($lockedReservation->hold_expires_at);
                $defaultExpiry = now()->addMinutes(15);
                $expiresAt = $holdExpiresAt->lessThan($defaultExpiry) ? $holdExpiresAt : $defaultExpiry;
            }
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

    public function instructions(OwnerPaymentAttempt $attempt): array
    {
        $receiver = $attempt->receiverSnapshot();
        $status = $attempt->status;
        if ($status === OwnerPaymentAttempt::STATUS_PENDING && $attempt->expires_at?->isPast()) {
            $status = OwnerPaymentAttempt::STATUS_EXPIRED;
        }
        $bankCode = strtoupper((string) $receiver['bank_code']);
        $bankCode = $bankCode === 'MBBANK' ? 'MB' : $bankCode;
        $accountNumber = (string) $receiver['account_number'];
        $query = http_build_query([
            'amount' => (int) $attempt->amount,
            'addInfo' => $attempt->reference,
            'accountName' => (string) $receiver['account_name'],
        ], '', '&', PHP_QUERY_RFC3986);
        $qrPath = rawurlencode($bankCode).'-'.rawurlencode($accountNumber).'-compact2.png';

        return [
            'id' => (int) $attempt->id,
            'provider' => $attempt->provider,
            'reference' => $attempt->reference,
            'transfer_content' => $attempt->reference,
            'amount' => (int) $attempt->amount,
            'currency' => $attempt->currency,
            'status' => $status,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'paid_at' => $attempt->paid_at?->toIso8601String(),
            'receiver' => [
                'bank_code' => $receiver['bank_code'],
                'account_name' => $receiver['account_name'],
                'account_number' => $accountNumber,
            ],
            'qr_url' => 'https://img.vietqr.io/image/'.$qrPath.'?'.$query,
        ];
    }
}

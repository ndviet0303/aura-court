<?php

namespace App\Services\Reservation;

use App\Services\Infrastructure\ObservabilityService;

use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(private readonly PriceQuoteService $quotes) {}

    public function hold(User $user, int $organizationId, array $data): Reservation
    {
        return $this->transactionWithConflictHandling(function () use ($user, $organizationId, $data): Reservation {
            $quote = $this->findQuote($user->id, $organizationId, $data);
            if (! $quote) {
                $quote = $this->quotes->create(
                    $user->id,
                    $organizationId,
                    (int) ($data['court_id'] ?? 0),
                    (array) ($data['slots'] ?? []),
                );
                $quote = PriceQuote::query()->withoutGlobalScope('tenant_isolation')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            }

            $organizationId = $quote->organization_id;

            if ($quote->status !== 'open') {
                abort(409, 'Price quote has already been accepted.');
            }
            if ($quote->expires_at?->isPast()) {
                abort(409, 'Price quote has expired.');
            }

            $reservation = Reservation::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'court_id' => $quote->court_id,
                'price_quote_id' => $quote->id,
                'code' => $this->uniqueCode(),
                'status' => 'held',
                'currency' => $quote->currency,
                'total_amount' => $quote->total_amount,
                'hold_expires_at' => $quote->expires_at,
            ]);

            foreach ($quote->slot_snapshot as $slotSnapshot) {
                $slot = ReservationSlot::create([
                    'organization_id' => $organizationId,
                    'reservation_id' => $reservation->id,
                    'court_id' => $quote->court_id,
                    'starts_at' => $slotSnapshot['starts_at'],
                    'ends_at' => $slotSnapshot['ends_at'],
                    'unit_price' => $slotSnapshot['unit_price'],
                    'status' => 'held',
                    'snapshot' => $slotSnapshot,
                ]);

                ReservationEntitlement::create([
                    'organization_id' => $organizationId,
                    'reservation_id' => $reservation->id,
                    'reservation_slot_id' => $slot->id,
                    'user_id' => $user->id,
                    'court_id' => $quote->court_id,
                    'starts_at' => $slot->starts_at,
                    'ends_at' => $slot->ends_at,
                    'amount' => $slot->unit_price,
                    'status' => 'held',
                ]);
            }

            $quote->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_snapshot' => [
                    'quote_token' => $quote->quote_token,
                    'rule_version' => $quote->rule_version,
                    'slot_snapshot' => $quote->slot_snapshot,
                    'subtotal' => $quote->subtotal,
                    'total_amount' => $quote->total_amount,
                ],
            ]);

            return $reservation->load(['court.venue', 'quote', 'slots', 'entitlements']);
        });
    }

    public function confirm(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($reservation->status === 'confirmed') {
                return $reservation->load(['court.venue', 'quote', 'slots', 'entitlements']);
            }
            if ($reservation->status !== 'held') {
                abort(409, 'Reservation is not awaiting confirmation.');
            }
            if ($reservation->hold_expires_at?->isPast()) {
                $this->release($reservation, 'expired');
                abort(409, 'Reservation hold has expired.');
            }

            $reservation->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            $reservation->slots()->where('status', 'held')->update(['status' => 'confirmed']);
            $reservation->entitlements()->where('status', 'held')->update(['status' => 'active']);

            return $reservation->fresh(['court.venue', 'quote', 'slots', 'entitlements']);
        });
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (in_array($reservation->status, ['cancelled', 'expired'], true)) {
                return $reservation->load(['court.venue', 'slots', 'entitlements']);
            }
            $this->release($reservation, 'cancelled');

            return $reservation->fresh(['court.venue', 'slots', 'entitlements']);
        });
    }

    private function findQuote(int $userId, int $organizationId, array $data): ?PriceQuote
    {
        $query = PriceQuote::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('user_id', $userId)
            ->where('status', 'open');

        if (! empty($data['quote_token'])) {
            $query->where('quote_token', $data['quote_token']);
        } elseif (! empty($data['quote_id'])) {
            $query->whereKey($data['quote_id']);
        } else {
            return null;
        }

        return $query->lockForUpdate()->firstOrFail();
    }

    private function release(Reservation $reservation, string $status): void
    {
        $reservation->update([
            'status' => $status,
            'cancelled_at' => $status === 'cancelled' ? now() : $reservation->cancelled_at,
        ]);
        $reservation->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => $status]);
        $reservation->entitlements()->whereIn('status', ['held', 'active'])->update(['status' => $status]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'RSV'.strtoupper(Str::random(13));
        } while (Reservation::query()->where('code', $code)->exists());

        return $code;
    }

    private function transactionWithConflictHandling(callable $operation): Reservation
    {
        try {
            return DB::transaction($operation);
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'reservation_entitlements_active_unique')
                || (str_contains($message, 'reservation_entitlements') && str_contains($message, 'unique'))) {
                app(ObservabilityService::class)->metric('reservation.overbooking_conflict');
                abort(409, 'The selected court-time is no longer available.');
            }
            throw $exception;
        }
    }
}

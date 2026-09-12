<?php

namespace App\Services\Payment;

use App\Services\Reservation\ReservationService;

use App\Models\Reservation;
use App\Models\SplitGroup;
use App\Models\SplitShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SplitPaymentService
{
    public function __construct(private readonly ReservationService $reservations) {}

    public function create(Reservation $reservation, User $host, array $shares): SplitGroup
    {
        $count = count($shares);
        if ($count < 2 || $count > 8) {
            throw ValidationException::withMessages(['shares' => 'A split group must have between two and eight shares.']);
        }
        $amounts = array_map(static fn ($share) => (int) (is_array($share) ? ($share['amount'] ?? 0) : $share), $shares);
        if (array_sum($amounts) !== (int) $reservation->total_amount || min($amounts) <= 0) {
            throw ValidationException::withMessages(['shares' => 'Split shares must be positive and sum exactly to the reservation amount.']);
        }

        return DB::transaction(function () use ($reservation, $host, $amounts): SplitGroup {
            $group = SplitGroup::create([
                'organization_id' => $reservation->organization_id,
                'reservation_id' => $reservation->id,
                'host_user_id' => $host->id,
                'expected_amount' => $reservation->total_amount,
                'share_count' => count($amounts),
                'expires_at' => now()->addMinutes(15),
                'status' => 'pending',
            ]);
            foreach ($amounts as $index => $amount) {
                SplitShare::create([
                    'organization_id' => $reservation->organization_id,
                    'split_group_id' => $group->id,
                    'share_number' => $index + 1,
                    'amount' => $amount,
                    'expires_at' => $group->expires_at,
                ]);
            }
            return $group->load('shares');
        });
    }

    public function payShare(SplitShare $share, User $payer, int $amount, string $paymentReference): SplitGroup
    {
        return DB::transaction(function () use ($share, $payer, $amount, $paymentReference): SplitGroup {
            $share = SplitShare::query()->whereKey($share->id)->lockForUpdate()->firstOrFail();
            $group = SplitGroup::query()->whereKey($share->split_group_id)->lockForUpdate()->firstOrFail();
            abort_unless($share->organization_id === $group->organization_id, 409);
            abort_unless($group->status === 'pending', 409, 'Split group is not awaiting payment.');
            abort_if($group->expires_at?->isPast() || $share->expires_at?->isPast(), 409, 'Split share has expired.');
            abort_unless($amount === $share->amount, 422, 'A split share must be paid in full.');
            abort_unless($share->status === 'pending', 409, 'Split share has already been paid.');

            $share->update(['payer_id' => $payer->id, 'paid_amount' => $amount, 'payment_reference' => $paymentReference, 'status' => 'paid', 'paid_at' => now()]);
            $group->update(['paid_amount' => $group->paid_amount + $amount]);
            if ($group->paid_amount === $group->expected_amount) {
                $reservation = $group->reservation()->lockForUpdate()->firstOrFail();
                if ($reservation->status === 'held') {
                    $this->reservations->confirm($reservation);
                }
                $group->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            }
            return $group->fresh('shares', 'reservation');
        });
    }

    public function coverShortfall(SplitGroup $group, User $host, string $paymentReference): SplitGroup
    {
        return DB::transaction(function () use ($group, $host, $paymentReference): SplitGroup {
            $group = SplitGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            abort_unless($group->host_user_id === $host->id, 403);
            abort_unless($group->status === 'pending', 409);
            $shortfall = $group->expected_amount - $group->paid_amount;
            abort_if($shortfall <= 0, 409, 'Split group has no shortfall.');
            $group->update(['paid_amount' => $group->expected_amount, 'host_covers_shortfall' => true, 'status' => 'confirmed', 'confirmed_at' => now()]);
            $reservation = $group->reservation()->lockForUpdate()->firstOrFail();
            if ($reservation->status === 'held') {
                $this->reservations->confirm($reservation);
            }
            return $group->fresh('shares', 'reservation');
        });
    }

    public function expire(SplitGroup $group): SplitGroup
    {
        return DB::transaction(function () use ($group): SplitGroup {
            $group = SplitGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($group->status !== 'pending' || $group->expires_at?->isFuture()) {
                return $group;
            }
            $group->update(['status' => 'expired']);
            $this->reservations->release($group->reservation, 'cancelled');
            $group->shares()->where('status', 'pending')->update(['status' => 'expired']);
            return $group->fresh('shares', 'reservation');
        });
    }

    public function refundAllocations(SplitGroup $group): array
    {
        return $group->shares()->where('status', 'paid')->get()->mapWithKeys(fn (SplitShare $share) => [$share->id => ['payer_id' => $share->payer_id, 'amount' => $share->paid_amount, 'payment_reference' => $share->payment_reference]])->all();
    }
}

<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Services\Booking\WaitlistService;
use Illuminate\Support\Facades\DB;

/**
 * BR-84: the customer cancels their own booking while it is still pending payment (design D1). The status is read
 * again under the payment locks, so of this and a matching transfer only the first to commit takes effect.
 */
class CustomerPaymentCancel
{
    public function __construct(
        private readonly HeldReservationRelease $release,
        private readonly WaitlistService $waitlists,
    ) {}

    public function cancel(Reservation $reservation, User $customer): Reservation
    {
        abort_unless((int) $reservation->user_id === (int) $customer->id, 403);

        return DB::transaction(function () use ($reservation): Reservation {
            $locked = $this->release->lock($reservation);
            abort_unless($locked instanceof Reservation, 404);
            if (in_array($locked->status, ['cancelled', 'expired'], true)) {
                return $locked;
            }
            if ($locked->status !== 'held') {
                abort(response()->json([
                    'message' => 'Đơn không còn chờ thanh toán nên không thể hủy.',
                    'code' => 'RESERVATION_NOT_PENDING',
                ], 409));
            }

            $freed = ReservationSlot::query()->where('reservation_id', $locked->id)->where('status', 'held')->get(['court_id', 'starts_at']);
            $this->release->releaseHeld($locked, 'cancelled', 'customer_payment_cancel');
            DB::afterCommit(fn () => $freed->each(fn ($slot) => $this->waitlists->notifyForSlot((int) $slot->court_id, $slot->starts_at)));

            return $locked;
        });
    }
}

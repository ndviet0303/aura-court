<?php

namespace App\Listeners;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Events\PromotionProgramChanged;
use App\Models\Reservation;
use App\Services\Booking\WaitlistService;
use App\Services\Promotion\VoucherUsageService;
use App\Services\Reservation\HeldReservationRelease;
use Illuminate\Support\Facades\DB;

/**
 * FR-62, FR-63, BR-71 (design D19): after the owner's update or stop commits, every booking pending payment that holds
 * a use of the program is checked again as consume would check it now, its own use counted as its own (D17). A voucher
 * that no longer applies, a different discount or a payment deadline after the program's end expires it as
 * voucher_changed and tells the customer; otherwise it stays as it is. One transaction per booking (D11), so no two
 * bookings are locked together and one failure rolls back no other. releaseHeld also releases the voucher use. A transfer
 * arriving first is refused by consume on module 4's confirmation paths.
 */
class RecheckHeldReservationsAtProgramChange
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly HeldReservationRelease $release,
        private readonly VoucherUsageService $vouchers,
        private readonly WaitlistService $waitlists,
    ) {}

    public function handle(PromotionProgramChanged $event): void
    {
        foreach ($this->reservations->heldWithVoucherOf($event->programId) as $candidate) {
            rescue(fn () => DB::transaction(fn () => $this->recheck($candidate)));
        }
    }

    private function recheck(Reservation $candidate): void
    {
        $locked = $this->release->lock($candidate);
        if (! $locked || $locked->status !== 'held' || $this->vouchers->stillHolds($locked)) {
            return;
        }

        $freed = $this->reservations->unreleasedSlots((int) $locked->id);
        if (! $this->release->releaseHeld($locked, 'expired', 'voucher_changed')) {
            return;
        }
        $this->release->notifyReleased($locked, 'voucher_changed');
        DB::afterCommit(fn () => $freed->each(fn ($slot) => $this->waitlists->notifyForSlot((int) $slot->court_id, $slot->starts_at)));
    }
}

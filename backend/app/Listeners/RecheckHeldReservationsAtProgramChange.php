<?php

namespace App\Listeners;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
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
 * bookings are locked together and one failure rolls back no other. A transfer arriving first is refused by consume
 * once module 4's confirmation paths call it (their Pha 5; not on this branch yet).
 */
class RecheckHeldReservationsAtProgramChange
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly HeldReservationRelease $release,
        private readonly VoucherUsageService $vouchers,
        private readonly WaitlistService $waitlists,
        private readonly DurableNotificationServiceInterface $notifications,
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
        // ponytail: releaseHeld does not release the voucher use until module 4's task 5.5 merges; release is
        // idempotent, so drop this line then and keep theirs.
        $this->vouchers->release($locked);
        // Same key, event and text as module 4's notice for voucher_changed, so the outbox sends it once.
        $this->notifications->recordOutboxNotification(
            key: "reservation.notification.{$locked->id}.voucher_changed",
            userId: (int) $locked->user_id,
            eventType: 'booking.voucher_changed',
            payload: [
                'booking_id' => $locked->id,
                'booking_code' => $locked->code,
                'event' => 'voucher_changed',
                'title' => 'Voucher không còn áp dụng',
                'body' => "Voucher của đơn {$locked->code} không còn áp dụng như lúc đặt nên yêu cầu đặt sân này không còn hiệu lực. Vui lòng gửi lại yêu cầu.",
                'action_url' => '/booking',
                'channels' => ['database', 'mail'],
                'delivered_channels' => [],
                'mail_view' => 'emails.booking-notification',
                'aggregate_type' => Reservation::class,
                'aggregate_id' => $locked->id,
            ],
            orgId: (int) $locked->organization_id,
        );
        DB::afterCommit(fn () => $freed->each(fn ($slot) => $this->waitlists->notifyForSlot((int) $slot->court_id, $slot->starts_at)));
    }
}

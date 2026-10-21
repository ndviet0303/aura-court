<?php

namespace App\Listeners;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Events\CourtPricesTookEffect;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Services\Booking\WaitlistService;
use App\Services\Pricing\RentalPriceCalculator;
use App\Services\Reservation\HeldReservationRelease;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-87 (design D11): when new prices take effect, every booking pending payment on those courts that ends after
 * that time is priced again. A different amount payable expires it as a price change and tells the customer to
 * submit again; the same amount keeps it and its payment deadline. One transaction per booking, so one failure
 * rolls back no other and no two bookings are locked together.
 */
class RecheckHeldReservationsAtNewPrice
{
    public function __construct(
        private readonly HeldReservationRelease $release,
        private readonly RentalPriceCalculator $rentals,
        private readonly WaitlistService $waitlists,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function handle(CourtPricesTookEffect $event): void
    {
        $candidates = Reservation::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('status', 'held')
            // A recurring occurrence keeps the price of its contract and monthly invoice.
            ->whereDoesntHave('recurringOccurrence')
            ->whereHas('slots', fn ($slots) => $slots->whereIn('court_id', $event->courtIds)->where('ends_at', '>', $event->effectiveAt->utc()))
            ->orderBy('id')
            ->get(['id', 'code']);
        foreach ($candidates as $candidate) {
            rescue(fn () => DB::transaction(fn () => $this->recheck($candidate)));
        }
    }

    private function recheck(Reservation $candidate): void
    {
        $locked = $this->release->lock($candidate);
        if (! $locked || $locked->status !== 'held') {
            return;
        }

        $slots = ReservationSlot::query()->withoutGlobalScope('tenant_isolation')->where('reservation_id', $locked->id)->get();
        if ($this->payable($locked, $slots->all()) === (int) $locked->total_amount
            || ! $this->release->releaseHeld($locked, 'expired', 'price_changed')) {
            return;
        }

        $this->notifications->recordOutboxNotification(
            key: "reservation.notification.{$locked->id}.price_changed",
            userId: (int) $locked->user_id,
            eventType: 'booking.price_changed',
            payload: [
                'booking_id' => $locked->id,
                'booking_code' => $locked->code,
                'event' => 'price_changed',
                'title' => 'Giá sân đã thay đổi',
                'body' => "Giá sân của đơn {$locked->code} đã thay đổi nên yêu cầu đặt sân này không còn hiệu lực. Vui lòng xem giá mới và gửi lại yêu cầu.",
                'action_url' => '/booking',
                'channels' => ['database', 'mail'],
                'delivered_channels' => [],
                'mail_view' => 'emails.booking-notification',
                'aggregate_type' => Reservation::class,
                'aggregate_id' => $locked->id,
            ],
            orgId: (int) $locked->organization_id,
        );
        DB::afterCommit(fn () => $slots->each(fn ($slot) => $this->waitlists->notifyForSlot((int) $slot->court_id, $slot->starts_at)));
    }

    /**
     * What the booking would pay now, or null when one of its intervals has no price any more.
     *
     * @param  list<ReservationSlot>  $slots
     */
    private function payable(Reservation $locked, array $slots): ?int
    {
        $court = Court::query()->withoutGlobalScope('tenant_isolation')->findOrFail($locked->court_id);
        try {
            // ponytail: no voucher yet; task 5.5 passes the voucher use the booking holds once module 3 records it.
            return array_sum(array_map(fn (ReservationSlot $slot): int => $this->rentals->quote(
                $court,
                CarbonImmutable::parse($slot->starts_at),
                CarbonImmutable::parse($slot->ends_at),
                null,
                $locked,
            )->total, $slots));
        } catch (ValidationException) {
            return null;
        }
    }
}

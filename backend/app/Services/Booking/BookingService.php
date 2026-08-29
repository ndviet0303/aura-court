<?php

namespace App\Services\Booking;

use App\Domain\Booking\Actions\ApplyBookingCoupon;
use App\Domain\Booking\Actions\CancelBooking;
use App\Domain\Booking\Actions\CancelBookingSlots;
use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Actions\CreateRecurringBooking;
use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Domain\Booking\Actions\NotifyExpiringBookings;
use App\Domain\Booking\Actions\RescheduleBooking;
use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\Booking;

class BookingService
{
    public function __construct(
        private readonly CreateBooking $createBookingAction,
        private readonly CreateRecurringBooking $createRecurringBookingAction,
        private readonly CancelBooking $cancelBookingAction,
        private readonly CancelBookingSlots $cancelBookingSlotsAction,
        private readonly RescheduleBooking $rescheduleBookingAction,
        private readonly ApplyBookingCoupon $applyBookingCouponAction,
        private readonly ExpirePendingBookings $expirePendingBookingsAction,
        private readonly NotifyExpiringBookings $notifyExpiringBookingsAction,
    ) {}

    public function create(int $userId, int $courtId, array $startsAt, ?string $couponCode = null): Booking
    {
        return $this->createBookingAction->execute(
            new CreateBookingData($userId, $courtId, $startsAt, $couponCode)
        );
    }

    public function applyCoupon(Booking $booking, ?string $couponCode): Booking
    {
        return $this->applyBookingCouponAction->execute($booking, $couponCode);
    }

    public function expirePending(): int
    {
        return $this->expirePendingBookingsAction->execute();
    }

    public function notifyExpiring(): int
    {
        return $this->notifyExpiringBookingsAction->execute();
    }

    public function cancel(Booking $booking, ?string $reason = null): Booking
    {
        return $this->cancelBookingAction->execute($booking, $reason);
    }

    public function cancelSlots(Booking $booking, array $slotIds, ?string $reason = null): Booking
    {
        return $this->cancelBookingSlotsAction->execute($booking, $slotIds, $reason);
    }

    public function createRecurring(int $userId, int $courtId, array $startsAt, int $weeks): array
    {
        return $this->createRecurringBookingAction->execute($userId, $courtId, $startsAt, $weeks);
    }

    public function reschedule(Booking $booking, int $courtId, array $startsAt): Booking
    {
        return $this->rescheduleBookingAction->execute($booking, $courtId, $startsAt);
    }
}

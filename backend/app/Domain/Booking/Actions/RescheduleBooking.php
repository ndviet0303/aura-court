<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescheduleBooking
{
    public function __construct(
        private readonly CreateBooking $createBookingAction,
        private readonly CancelBooking $cancelBookingAction,
    ) {}

    public function execute(Booking $booking, int $courtId, array $startsAt): Booking
    {
        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking không thể đổi lịch.']);
        }
        $firstSlot = $booking->slots()->orderBy('starts_at')->first();
        if ($booking->status === 'confirmed' && $firstSlot && now()->greaterThan($firstSlot->starts_at->copy()->subHours(2))) {
            abort(409, 'Booking chỉ được đổi trước giờ chơi ít nhất 2 giờ.');
        }

        return DB::transaction(function () use ($booking, $courtId, $startsAt) {
            $replacement = $this->createBookingAction->execute(
                new CreateBookingData($booking->user_id, $courtId, $startsAt)
            );
            $this->cancelBookingAction->execute($booking, 'Đổi lịch sang booking '.$replacement->code);

            return $replacement;
        });
    }
}

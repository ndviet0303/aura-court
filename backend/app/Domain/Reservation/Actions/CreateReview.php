<?php

namespace App\Domain\Reservation\Actions;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Domain\Reservation\DTOs\CreateReviewData;
use App\Models\Review;

class CreateReview
{
    private const REVIEWABLE_STATUSES = ['confirmed', 'in_use', 'completed'];

    // A legacy mirror is judged by the status its V1 booking had (V1 allowed confirmed and no_show).
    private const REVIEWABLE_LEGACY_STATUSES = ['confirmed', 'no_show', 'completed'];

    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
    ) {}

    public function execute(CreateReviewData $data): Review
    {
        $reservation = $this->reservations->findOwnedByCode($data->userId, $data->reservationCode);
        abort_if($reservation === null, 404, 'Không tìm thấy lượt đặt sân.');
        $mapping = $reservation->legacyMapping->first();
        $reviewable = in_array($reservation->status, self::REVIEWABLE_STATUSES, true)
            || ($reservation->status === 'legacy' && in_array($mapping?->getAttribute('legacy_status'), self::REVIEWABLE_LEGACY_STATUSES, true));
        abort_unless($reviewable, 422, 'Chỉ có thể đánh giá booking đã hoàn tất.');
        // ends_at is the latest slot end, so this means every slot has ended; no slots gives null.
        abort_unless($reservation->ends_at?->isPast() === true, 422, 'Chỉ có thể đánh giá sau khi đã chơi xong.');
        abort_if($reservation->review !== null, 409, 'Booking này đã được đánh giá.');

        return $this->reservations->createReview([
            'user_id' => $data->userId,
            'court_id' => $reservation->court_id,
            'reservation_id' => $reservation->id,
            // Keeps the V1 history link for mapped bookings; the request never carries booking fields.
            'booking_id' => $mapping?->getAttribute('legacy_booking_id'),
            'rating' => $data->rating,
            'body' => $data->body,
        ])->load('user:id,name');
    }
}

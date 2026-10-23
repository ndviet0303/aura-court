<?php

namespace App\Domain\Review\Actions;

use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Domain\Review\DTOs\ReviewData;
use App\Models\BookingAttendance;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * FR-116, BR-147: the booker of a completed reservation (attendance checked out) reviews its venue once, within
 * 7 days of the check-out. Pending, confirmed, in-use, no-show and incident-ended bookings are refused, and a
 * reservation whose review was deleted never gets another one.
 */
class CreateReview
{
    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    public function execute(User $user, string $reservationCode, ReviewData $data): Review
    {
        $reservation = $this->reviews->findOwnedReservation((int) $user->id, $reservationCode);
        if ($reservation === null) {
            abort(404, 'Không tìm thấy lượt đặt sân.');
        }
        $attendance = $reservation->attendance;
        if ($attendance?->status !== BookingAttendance::STATUS_CHECKED_OUT || $attendance->checked_out_at === null) {
            CommunityAccess::fail('REVIEW_NOT_COMPLETED', 'Chỉ đánh giá được đơn đã hoàn thành.');
        }
        if ($reservation->review !== null) {
            CommunityAccess::fail('REVIEW_EXISTS', 'Đơn này đã có đánh giá; đánh giá đã xoá không tạo lại được.', 409);
        }
        if (now()->greaterThan($attendance->checked_out_at->copy()->addDays(Review::WINDOW_DAYS))) {
            CommunityAccess::fail('REVIEW_WINDOW_CLOSED', 'Đã quá 7 ngày kể từ khi đơn hoàn thành.');
        }

        try {
            return $this->reviews->create([
                'user_id' => $user->id,
                'venue_id' => $reservation->court->venue_id,
                'court_id' => $reservation->court_id,
                'reservation_id' => $reservation->id,
                'is_published' => true,
            ] + $data->attributes());
        } catch (UniqueConstraintViolationException) {
            CommunityAccess::fail('REVIEW_EXISTS', 'Đơn này đã có đánh giá; đánh giá đã xoá không tạo lại được.', 409);
        }
    }
}

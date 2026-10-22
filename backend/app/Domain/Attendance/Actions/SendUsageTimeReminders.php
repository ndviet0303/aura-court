<?php

namespace App\Domain\Attendance\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\Attendance\Rules\UsageTime;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;

/**
 * FR-105, BR-131, D9: a booking in use gets a web notice to its venue's operators 10 minutes before its booked end and
 * once it is overdue, never both if a run was missed. The key carries the booked end, so a rerun writes nothing. It
 * never checks out or completes a booking.
 */
class SendUsageTimeReminders
{
    public function __construct(
        private readonly BookingAttendanceRepositoryInterface $attendances,
        private readonly UserRepositoryInterface $users,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(): void
    {
        $now = now();
        // ponytail: bookings overdue for over a day are left out; a forgotten check-out stays on the in-use list instead.
        foreach ($this->attendances->inUseEndingBetween($now->copy()->subDay(), $now->copy()->addMinutes(UsageTime::ENDING_SOON_MINUTES)) as $reservation) {
            $flag = UsageTime::flag($reservation->ends_at, $now);
            if ($flag !== null) {
                $this->notifyOperators($reservation, $flag);
            }
        }
    }

    private function notifyOperators(Reservation $reservation, string $flag): void
    {
        $venue = $reservation->court->venue;
        $courtCode = $reservation->court->code;
        $endsAt = $reservation->ends_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y');
        [$title, $body] = $flag === UsageTime::OVERDUE
            ? ["Sân {$courtCode} đã hết giờ, chưa check-out", "Đơn {$reservation->code} tại {$venue->name}, sân {$courtCode} đã hết giờ lúc {$endsAt} nhưng chưa check-out. Nhắc khách trả sân và check-out khi khách đã trả."]
            : ["Sân {$courtCode} còn 10 phút", "Đơn {$reservation->code} tại {$venue->name}, sân {$courtCode} kết thúc lúc {$endsAt}. Nhắc khách chuẩn bị trả sân."];

        foreach ($this->users->activeVenueOperatorIds((int) $reservation->organization_id, (int) $venue->id) as $userId) {
            try {
                $this->notif->recordOutboxNotification(
                    key: "attendance.reminder.{$reservation->id}.{$reservation->ends_at->getTimestamp()}.{$flag}.{$userId}",
                    userId: $userId,
                    eventType: 'attendance.reminder',
                    payload: [
                        'title' => $title,
                        'body' => $body,
                        'action_url' => "/admin/operations?venue={$venue->id}",
                        'action_text' => 'Mở màn vận hành',
                        'delivered_channels' => [],
                        'venue_id' => $venue->id,
                        'court_code' => $courtCode,
                        'reservation_code' => $reservation->code,
                        'ends_at' => $reservation->ends_at->toISOString(),
                        'kind' => $flag,
                        'aggregate_type' => Reservation::class,
                        'aggregate_id' => $reservation->id,
                    ],
                    orgId: (int) $reservation->organization_id,
                    channels: ['database'],
                );
            } catch (\Throwable $e) {
                // The outbox row is already pending, so scheduled recovery resends it; keep notifying the others.
                Log::warning("Usage time reminder failed immediately for reservation {$reservation->id}, user {$userId}: {$e->getMessage()}", [
                    'reservation_id' => $reservation->id,
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
        }
    }
}

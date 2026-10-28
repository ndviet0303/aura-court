<?php

namespace App\Domain\BookingTransfer\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Models\BookingTransfer;
use App\Models\BookingTransferItem;
use App\Models\User;

/**
 * D12, BR-191, FR-147: in-app notices through the outbox, recorded inside the caller's transaction so a rollback
 * sends nothing (BR-189). Created: the buyer. Completed: seller, buyer and the active owners of each booking's
 * organization, naming the bookings and the time. Cancelled: the other party.
 */
class NotifyTransferParties
{
    public const CREATED = 'created';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public function __construct(
        private readonly DurableNotificationServiceInterface $notifications,
        private readonly BookingTransferRepositoryInterface $transfers,
    ) {}

    /** $transfer comes with seller, buyer and items with their reservations. */
    public function execute(BookingTransfer $transfer, string $event, ?User $actor = null): void
    {
        $seller = (string) $transfer->seller->name;
        $buyer = (string) $transfer->buyer->name;
        $count = $transfer->items->count();
        $total = number_format((int) $transfer->total, 0, ',', '.').'₫';
        $url = "/resale/transfers/{$transfer->id}";

        if ($event === self::CREATED) {
            $this->send($transfer, $event, 'buyer', (int) $transfer->buyer_id, 'Bạn có giao dịch nhận chuyển nhượng',
                "{$seller} tạo giao dịch chuyển nhượng {$count} booking cho bạn, tổng {$total}. Hãy chuyển tiền trực tiếp cho người chuyển nhượng; booking chỉ thuộc về bạn sau khi họ xác nhận.", $url);

            return;
        }
        if ($event === self::CANCELLED) {
            $other = (int) $actor?->id === (int) $transfer->seller_id ? (int) $transfer->buyer_id : (int) $transfer->seller_id;
            $this->send($transfer, $event, 'other', $other, 'Giao dịch chuyển nhượng đã bị huỷ',
                "{$actor?->name} đã huỷ giao dịch chuyển nhượng {$count} booking (tổng {$total}).", $url);

            return;
        }

        $at = $transfer->confirmed_at?->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y');
        $codes = $transfer->items->map(fn (BookingTransferItem $item): string => (string) $item->reservation?->code)->implode(', ');
        $this->send($transfer, $event, 'seller', (int) $transfer->seller_id, 'Đã chuyển nhượng booking',
            "Bạn đã chuyển {$count} booking ({$codes}) cho {$buyer} lúc {$at}. Các booking này không còn trong danh sách của bạn.", $url);
        $this->send($transfer, $event, 'buyer', (int) $transfer->buyer_id, 'Bạn đã nhận chuyển nhượng booking',
            "{$seller} đã xác nhận chuyển {$count} booking ({$codes}) cho bạn lúc {$at}. Mã nhận sân mới có trong lịch đặt của bạn.", '/booking');
        foreach ($transfer->items->groupBy('organization_id') as $organizationId => $items) {
            $orgCodes = $items->map(fn (BookingTransferItem $item): string => (string) $item->reservation?->code)->implode(', ');
            foreach ($this->transfers->ownerIds((int) $organizationId) as $ownerId) {
                $this->send($transfer, $event, "owner.{$ownerId}", $ownerId, 'Booking đã được chuyển nhượng',
                    "Booking {$orgCodes} đã chuyển từ {$seller} sang {$buyer} lúc {$at}. {$buyer} là người có quyền sử dụng.", '/admin/operations', (int) $organizationId);
            }
        }
    }

    private function send(BookingTransfer $transfer, string $event, string $to, int $userId, string $title, string $body, string $url, ?int $orgId = null): void
    {
        $this->notifications->recordOutboxNotification(
            key: "booking_transfer.{$transfer->id}.{$event}.{$to}",
            userId: $userId,
            eventType: "booking_transfer.{$event}",
            payload: [
                'title' => $title,
                'body' => $body,
                'action_url' => $url,
                'aggregate_type' => BookingTransfer::class,
                'aggregate_id' => $transfer->id,
            ],
            orgId: $orgId,
        );
    }
}

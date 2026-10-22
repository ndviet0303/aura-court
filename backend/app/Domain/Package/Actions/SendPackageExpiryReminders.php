<?php

namespace App\Domain\Package\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Models\VenuePackage;
use Illuminate\Support\Facades\Log;

/**
 * D11: a package gets only the latest reminder point it has reached (7, 3 or 1 day before its end, or the end), once per
 * owner. The key carries ends_at, so a renewal starts a fresh set and a rerun writes nothing.
 */
class SendPackageExpiryReminders
{
    private const DAYS_BEFORE_END = [0, 1, 3, 7];

    public function __construct(
        private readonly VenuePackageRepositoryInterface $packages,
        private readonly UserRepositoryInterface $users,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(): void
    {
        $now = now()->utc();
        foreach ($this->packages->currentEndingBetween($now->copy()->subDay(), $now->copy()->addDays(7)) as $package) {
            // The scan starts 7 days before the end, so the 7-day point is always reached.
            $offset = collect(self::DAYS_BEFORE_END)->first(fn (int $days): bool => $package->ends_at->copy()->subDays($days)->lte($now));
            $this->notifyOwners($package, $offset);
        }
    }

    private function notifyOwners(VenuePackage $package, int $offset): void
    {
        $days = $package->term_days->value;
        $endsAt = $package->ends_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y');
        $venueName = $package->venue->name ?? "#{$package->venue_id}";
        [$title, $body] = $offset === 0
            ? ["Gói nền tảng của {$venueName} đã hết hạn", "Gói {$days} ngày của cơ sở {$venueName} đã hết hạn lúc {$endsAt}. Mua gói mới tại trang Gói nền tảng để cơ sở nhận đặt sân trở lại."]
            : ["Gói nền tảng của {$venueName} sắp hết hạn", "Gói {$days} ngày của cơ sở {$venueName} hết hạn lúc {$endsAt}. Gia hạn tại trang Gói nền tảng để cơ sở tiếp tục nhận đặt sân mới."];

        foreach ($this->users->activeOwnerIds($package->organization_id) as $userId) {
            try {
                $this->notif->recordOutboxNotification(
                    key: "package.reminder.{$package->id}.{$package->ends_at->getTimestamp()}.{$offset}.{$userId}",
                    userId: $userId,
                    eventType: 'package.reminder',
                    payload: [
                        'title' => $title,
                        'body' => $body,
                        'action_url' => '/partner/subscription',
                        'action_text' => 'Xem gói nền tảng',
                        'delivered_channels' => [],
                        'mail_view' => 'emails.booking-notification',
                        'venue_id' => $package->venue_id,
                        'term_days' => $days,
                        'ends_at' => $package->ends_at->toISOString(),
                        'days_before_end' => $offset,
                        'aggregate_type' => VenuePackage::class,
                        'aggregate_id' => $package->id,
                    ],
                    orgId: $package->organization_id,
                    channels: ['database', 'mail'],
                );
            } catch (\Throwable $e) {
                // The outbox row is already pending, so scheduled recovery resends it; keep notifying the other owners.
                Log::warning("Package reminder failed immediately for package {$package->id}, user {$userId}: {$e->getMessage()}", [
                    'venue_package_id' => $package->id,
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
        }
    }
}

<?php

namespace App\Domain\Venue\Rules;

use App\Models\Venue;
use Illuminate\Validation\ValidationException;

/**
 * D3: the one status-transition rule for venues and courts, which share the four statuses.
 * Allowed: inactive -> active (open), active -> paused (pause), paused -> active (resume),
 * inactive | active | paused -> closed (closure). Closed is final; anything else is 422.
 */
final class LifecycleGuard
{
    private const ALLOWED = [
        Venue::STATUS_INACTIVE => [Venue::STATUS_ACTIVE, Venue::STATUS_CLOSED],
        Venue::STATUS_ACTIVE => [Venue::STATUS_PAUSED, Venue::STATUS_CLOSED],
        Venue::STATUS_PAUSED => [Venue::STATUS_ACTIVE, Venue::STATUS_CLOSED],
    ];

    private const LABELS = [
        Venue::STATUS_INACTIVE => 'Chưa hoạt động',
        Venue::STATUS_ACTIVE => 'Hoạt động',
        Venue::STATUS_PAUSED => 'Tạm ngừng',
        Venue::STATUS_CLOSED => 'Ngừng kinh doanh',
    ];

    public static function allows(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    /** @throws ValidationException rendered as 422 under the "status" key */
    public static function assertTransition(string $from, string $to): void
    {
        if (self::allows($from, $to)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'Không thể chuyển từ trạng thái "%s" sang "%s".',
                self::LABELS[$from] ?? $from,
                self::LABELS[$to] ?? $to,
            ),
        ]);
    }

    /**
     * Open and resume both lead to active, but open applies only to inactive and resume only to paused.
     *
     * @throws ValidationException rendered as 422 under the "status" key
     */
    public static function assertCommandSource(string $from, string $source, string $to): void
    {
        self::assertTransition($from, $to);
        if ($from !== $source) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Lệnh này chỉ áp dụng cho trạng thái "%s"; trạng thái hiện tại là "%s".',
                    self::LABELS[$source] ?? $source,
                    self::LABELS[$from] ?? $from,
                ),
            ]);
        }
    }
}

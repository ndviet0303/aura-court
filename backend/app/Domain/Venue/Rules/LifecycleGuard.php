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

    /** @throws ValidationException rendered as 422 under the "status" key */
    public static function assertTransition(string $from, string $to): void
    {
        if (in_array($to, self::ALLOWED[$from] ?? [], true)) {
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
}

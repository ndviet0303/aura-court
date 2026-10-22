<?php

namespace App\Domain\Attendance\Rules;

use Carbon\CarbonInterface;

/** FR-104, BR-131: a booking in use is ending soon in its last 10 minutes and overdue from its booked end. */
final class UsageTime
{
    public const ENDING_SOON_MINUTES = 10;

    public const ENDING_SOON = 'ending_soon';

    public const OVERDUE = 'overdue';

    public static function flag(CarbonInterface $endsAt, CarbonInterface $now): ?string
    {
        if ($now->gte($endsAt)) {
            return self::OVERDUE;
        }

        return $now->gte($endsAt->copy()->subMinutes(self::ENDING_SOON_MINUTES)) ? self::ENDING_SOON : null;
    }
}

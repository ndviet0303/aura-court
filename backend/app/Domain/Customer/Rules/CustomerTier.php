<?php

namespace App\Domain\Customer\Rules;

/** BR-141 tier by completed bookings in the quarter, and BR-142 usage rate, which never decides the tier. */
final class CustomerTier
{
    public const BRONZE = 'bronze';

    public const SILVER = 'silver';

    public const GOLD = 'gold';

    public const DIAMOND = 'diamond';

    public static function for(int $completed): string
    {
        return match (true) {
            $completed >= 60 => self::DIAMOND,
            $completed >= 35 => self::GOLD,
            $completed >= 15 => self::SILVER,
            default => self::BRONZE,
        };
    }

    /** Percent with one decimal; null ("Chưa có dữ liệu") when the customer has neither completed nor missed a booking. */
    public static function usageRate(int $completed, int $noShow): ?float
    {
        $total = $completed + $noShow;

        return $total === 0 ? null : round($completed * 100 / $total, 1);
    }
}

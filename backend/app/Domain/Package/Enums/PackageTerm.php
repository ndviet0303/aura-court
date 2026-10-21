<?php

namespace App\Domain\Package\Enums;

use Carbon\CarbonInterface;

/** The four package terms in days (BR-92); every term grants the same functions. */
enum PackageTerm: int
{
    case Days30 = 30;
    case Days90 = 90;
    case Days180 = 180;
    case Days360 = 360;

    /** D2: a day is 24 hours, so the end is counted in UTC, where no clock change shortens or lengthens a day. */
    public function endsAt(CarbonInterface $base): CarbonInterface
    {
        return $base->copy()->utc()->addDays($this->value);
    }
}

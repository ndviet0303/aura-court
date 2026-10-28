<?php

namespace App\Domain\Package\DTOs;

use App\Models\PackagePrice;

/** UC-5.3 E-3: the saved price, and whether a notice to the owners could not be queued. */
readonly class ScheduledPackagePrice
{
    public function __construct(
        public PackagePrice $price,
        public bool $notificationFailed,
    ) {}
}

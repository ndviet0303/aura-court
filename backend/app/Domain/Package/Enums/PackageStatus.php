<?php

namespace App\Domain\Package\Enums;

/** A venue's package status at a moment, derived from its current package row (D1); never stored. */
enum PackageStatus: string
{
    case None = 'none';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
}

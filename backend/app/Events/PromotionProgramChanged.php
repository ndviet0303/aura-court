<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The owner updated or stopped a promotion program (design D19). It waits for the outermost transaction to commit
 * and is dropped on rollback; the bookings pending payment that hold the program's voucher are checked again on it.
 */
class PromotionProgramChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $programId) {}
}

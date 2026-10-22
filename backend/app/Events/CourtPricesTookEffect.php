<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Prices of the courts changed for play from $effectiveAt on (design D16): sent once when a version is published
 * and when courts' links are applied, replaced or ended, never when an effective time arrives. It waits for the
 * outermost transaction to commit and is dropped on rollback. Module 4 re-checks bookings pending payment on it.
 */
class CourtPricesTookEffect implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @var list<int> sorted, unique */
    public readonly array $courtIds;

    /** @param  list<int>  $courtIds */
    public function __construct(array $courtIds, public readonly CarbonImmutable $effectiveAt)
    {
        $courtIds = array_values(array_unique(array_map('intval', $courtIds)));
        sort($courtIds);
        $this->courtIds = $courtIds;
    }
}

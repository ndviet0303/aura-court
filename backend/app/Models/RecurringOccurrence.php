<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'recurring_series_id', 'reservation_id', 'sequence', 'starts_at', 'ends_at', 'amount', 'status', 'released_at'])]
class RecurringOccurrence extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'amount' => 'integer', 'released_at' => 'datetime'];
    }

    /** @return BelongsTo<RecurringSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(RecurringSeries::class, 'recurring_series_id');
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}

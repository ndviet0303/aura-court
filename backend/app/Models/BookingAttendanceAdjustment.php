<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-109, BR-136: one owner adjustment of a booking's operational record, never updated or deleted. created_at is the
 * adjustment time, kept apart from the check-in/out times it corrects in before/after.
 */
#[Fillable(['organization_id', 'reservation_id', 'adjusted_by', 'reason', 'before', 'after', 'created_at'])]
class BookingAttendanceAdjustment extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_id', 'court_id', 'scope_key', 'exception_date', 'name', 'type', 'intervals', 'timezone', 'created_by'])]
class VenueScheduleException extends Model
{
    protected function casts(): array
    {
        // A plain 'date' serializes as UTC midnight, e.g. 2026-10-21T17:00Z for 2026-10-22 in Asia/Ho_Chi_Minh.
        return ['exception_date' => 'date:Y-m-d', 'intervals' => 'array'];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}

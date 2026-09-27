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
        return ['exception_date' => 'date', 'intervals' => 'array'];
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

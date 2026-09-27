<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_id', 'court_id', 'scope_key', 'timezone', 'version', 'weekly_hours', 'created_by'])]
class VenueOperatingSchedule extends Model
{
    protected function casts(): array
    {
        return ['weekly_hours' => 'array', 'version' => 'integer'];
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

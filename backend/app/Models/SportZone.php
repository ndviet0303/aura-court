<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['venue_id', 'name', 'sport_type', 'slot_duration', 'description', 'is_active'])]
class SportZone extends Model
{
    protected function casts(): array
    {
        return [
            'slot_duration' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }
}

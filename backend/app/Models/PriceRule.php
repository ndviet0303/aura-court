<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['organization_id', 'venue_id', 'sport_type', 'court_id', 'type', 'weekday', 'exception_date', 'starts_at', 'ends_at', 'price', 'version', 'priority', 'effective_from', 'effective_until', 'is_active', 'metadata'])]
class PriceRule extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        // The public court list is cached by courts_version and hides courts without a price.
        static::saved(fn () => Cache::increment('courts_version'));
        static::deleted(fn () => Cache::increment('courts_version'));
    }

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'exception_date' => 'date',
            'price' => 'integer',
            'version' => 'integer',
            'priority' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
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

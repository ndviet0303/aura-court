<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['court_id', 'weekday', 'starts_at', 'ends_at', 'price', 'is_active'])]
class CourtPriceSlot extends Model
{
    protected static function booted(): void
    {
        // The public court list is cached by courts_version and hides courts without a price.
        static::saved(fn () => Cache::increment('courts_version'));
        static::deleted(fn () => Cache::increment('courts_version'));
    }

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'price' => 'integer', 'is_active' => 'boolean'];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}

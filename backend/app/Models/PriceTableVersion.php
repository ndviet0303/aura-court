<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Published, unchangeable content of a price table (design D1). It prices play from effective_at, which is
 * never before published_at, for bookings confirmed from published_at on (D6).
 */
#[Fillable(['price_table_id', 'published_at', 'effective_at'])]
class PriceTableVersion extends Model
{
    protected function casts(): array
    {
        return ['published_at' => UtcDateTime::class, 'effective_at' => UtcDateTime::class];
    }

    /** @return BelongsTo<PriceTable, $this> */
    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class);
    }

    /** @return HasMany<PriceTableInterval, $this> */
    public function intervals(): HasMany
    {
        return $this->hasMany(PriceTableInterval::class);
    }
}

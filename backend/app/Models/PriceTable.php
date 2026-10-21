<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A venue's named price table (design D1); its prices live in published versions, and it is never deleted. */
#[Fillable(['venue_id', 'name', 'kind', 'status'])]
class PriceTable extends Model
{
    public const KIND_NORMAL = 'normal';

    public const KIND_HOLIDAY = 'holiday';

    public const STATUS_ACTIVE = 'active';

    /** No new links or versions; links already made keep running (design D2). */
    public const STATUS_RETIRED = 'retired';

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<PriceTableVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PriceTableVersion::class);
    }

    /** @return HasMany<CourtPriceTableLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(CourtPriceTableLink::class);
    }
}

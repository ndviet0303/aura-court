<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One price of a version: [start_minute, end_minute) of the venue's local day, 1440 being the end of the day
 * (design D15), on ISO weekdays (normal table) or on Y-m-d dates (holiday table).
 */
#[Fillable(['price_table_version_id', 'weekdays', 'dates', 'start_minute', 'end_minute', 'price_per_hour'])]
class PriceTableInterval extends Model
{
    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'dates' => 'array',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
            'price_per_hour' => 'integer',
        ];
    }

    /** @return BelongsTo<PriceTableVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PriceTableVersion::class, 'price_table_version_id');
    }
}

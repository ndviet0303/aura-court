<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A court uses a price table over [starts_at, ends_at); one link per kind at any instant (design D2). */
#[Fillable(['court_id', 'price_table_id', 'kind', 'starts_at', 'ends_at'])]
class CourtPriceTableLink extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => UtcDateTime::class, 'ends_at' => UtcDateTime::class];
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** @return BelongsTo<PriceTable, $this> */
    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class);
    }
}

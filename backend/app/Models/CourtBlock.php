<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Retired by operational stops (design D5); rows were copied into maintenance stops. The contract migration drops the table. */
#[Fillable(['court_id', 'starts_at', 'ends_at', 'reason'])]
class CourtBlock extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => UtcDateTime::class, 'ends_at' => UtcDateTime::class];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}

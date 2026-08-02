<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['court_id', 'weekday', 'starts_at', 'ends_at', 'price', 'is_active'])]
class CourtPriceSlot extends Model
{
    protected function casts(): array
    {
        return ['weekday' => 'integer', 'price' => 'integer', 'is_active' => 'boolean'];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'venue_id', 'court_id', 'type', 'weekday', 'exception_date', 'starts_at', 'ends_at', 'price', 'version', 'priority', 'is_active', 'metadata'])]
class PriceRule extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'exception_date' => 'date',
            'price' => 'integer',
            'version' => 'integer',
            'priority' => 'integer',
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

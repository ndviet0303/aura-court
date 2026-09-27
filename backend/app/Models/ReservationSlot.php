<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['organization_id', 'reservation_id', 'court_id', 'starts_at', 'ends_at', 'unit_price', 'status', 'snapshot'])]
class ReservationSlot extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'unit_price' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(ReservationEntitlement::class);
    }
}

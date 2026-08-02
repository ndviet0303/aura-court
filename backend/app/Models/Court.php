<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'image_url', 'is_active'])]
class Court extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function priceSlots(): HasMany
    {
        return $this->hasMany(CourtPriceSlot::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CourtBlock::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'venue_id', 'description', 'image_url', 'is_active'])]
class Court extends Model
{
    protected static function booted(): void
    {
        // Keep model-level seeders and existing integrations compatible while
        // every persisted court still receives a real venue_id.
        static::creating(function (Court $court): void {
            if (! $court->venue_id) {
                $court->venue_id = Venue::query()->where('slug', 'aura-court')->value('id');
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function priceSlots(): HasMany
    {
        return $this->hasMany(CourtPriceSlot::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
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

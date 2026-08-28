<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'venue_id', 'sport_zone_id', 'description', 'image_url', 'is_active'])]
class Court extends Model
{
    protected static function booted(): void
    {
        // Keep model-level seeders and existing integrations compatible while
        // every persisted court still receives a real venue_id and clean slug.
        static::creating(function (Court $court): void {
            if (! $court->venue_id) {
                $court->venue_id = Venue::query()->where('slug', 'aura-court')->value('id');
            }
            if (! $court->slug && $court->name) {
                $court->slug = Str::slug($court->name);
            }
        });

        static::updating(function (Court $court): void {
            if ($court->isDirty('name') && ! $court->slug) {
                $court->slug = Str::slug($court->name);
            }
            if ($court->isDirty('is_active') && $court->venue_id) {
                \App\Events\CourtStatusUpdated::dispatch(
                    venueId: (int) $court->venue_id,
                    courtId: (int) $court->id,
                    status: $court->is_active ? 'available' : 'maintenance',
                    metadata: ['court_id' => $court->id, 'is_active' => $court->is_active]
                );
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

    public function sportZone(): BelongsTo
    {
        return $this->belongsTo(SportZone::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CourtBlock::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}

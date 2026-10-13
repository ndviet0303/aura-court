<?php

namespace App\Models;

use App\Events\CourtStatusUpdated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

#[Fillable(['name', 'slug', 'venue_id', 'sport_zone_id', 'description', 'image_url', 'amenities', 'surface_type', 'capacity', 'is_active'])]
class Court extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $appends = ['timezone'];

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
                CourtStatusUpdated::dispatch(
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
        return [
            'is_active' => 'boolean',
            'amenities' => 'array',
            'capacity' => 'integer',
        ];
    }

    public function getAmenitiesAttribute($value): array
    {
        if ($value !== null) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($decoded) && count($decoded) > 0) {
                return $decoded;
            }
        }
        if ($this->relationLoaded('venue') && $this->venue) {
            return $this->venue->amenities;
        }

        return ['parking', 'wifi', 'shower', 'cafe', 'shop', 'lounge'];
    }

    public function getTimezoneAttribute(): string
    {
        return $this->attributes['timezone']
            ?? ($this->relationLoaded('venue') ? $this->venue?->timezone : null)
            ?? 'Asia/Ho_Chi_Minh';
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

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where(ctype_digit((string) $value) ? 'id' : 'slug', $value)->first();
    }

    public function toArray()
    {
        $attributes = parent::toArray();

        if (request() && request()->is('api/admin/*')) {
            return $attributes;
        }

        foreach ($this->getTranslatableAttributes() as $field) {
            if (array_key_exists($field, $attributes)) {
                $translations = $this->getTranslations($field);
                $locale = app()->getLocale();
                $attributes[$field] = $translations[$locale]
                    ?? ($translations[config('app.fallback_locale')]
                    ?? ($translations['vi']
                    ?? ($translations['en']
                    ?? (is_array($translations) && count($translations) > 0 ? reset($translations) : ''))));
            }
        }

        return $attributes;
    }
}

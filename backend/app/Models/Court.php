<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['code', 'venue_id', 'sport_type', 'slot_duration', 'description', 'images', 'amenities', 'surface_type', 'capacity', 'status'])]
class Court extends Model
{
    use HasTranslations;

    /** D3: the same four statuses as venues; see Venue::STATUSES and LifecycleGuard. */
    public const STATUS_INACTIVE = Venue::STATUS_INACTIVE;

    public const STATUS_ACTIVE = Venue::STATUS_ACTIVE;

    public const STATUS_PAUSED = Venue::STATUS_PAUSED;

    public const STATUS_CLOSED = Venue::STATUS_CLOSED;

    public const STATUSES = Venue::STATUSES;

    public const SPORT_TYPES = ['badminton', 'football_5v5', 'football_7v7', 'tennis', 'pickleball', 'basketball', 'volleyball', 'table_tennis'];

    public const SLOT_DURATIONS = [30, 45, 60, 90, 120];

    public array $translatable = ['description'];

    /** Mirrors the column defaults so a court built in memory already has its sport and slot length. */
    protected $attributes = ['sport_type' => 'badminton', 'slot_duration' => 60, 'images' => '[]'];

    protected $appends = ['timezone'];

    /**
     * D9, D11: code replaced name and slug, images replaced the single image_url; those columns stay unread until the
     * contract step. code_normalized only backs the code's uniqueness within the venue.
     */
    protected $hidden = ['name', 'slug', 'image_url', 'code_normalized'];

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
        return [
            'amenities' => 'array',
            'images' => 'array',
            'capacity' => 'integer',
            'slot_duration' => 'integer',
        ];
    }

    // ponytail: read-only mirror of status for frontend views that still read is_active; drop it with the is_active column once they read status.
    public function getIsActiveAttribute(): bool
    {
        return ($this->attributes['status'] ?? null) === self::STATUS_ACTIVE;
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

        return [];
    }

    public function getTimezoneAttribute(): string
    {
        return $this->attributes['timezone']
            ?? ($this->relationLoaded('venue') ? $this->venue?->timezone : null)
            ?? 'Asia/Ho_Chi_Minh';
    }

    /** @return HasMany<CourtPriceTableLink, $this> */
    public function priceTableLinks(): HasMany
    {
        return $this->hasMany(CourtPriceTableLink::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CourtBlock::class);
    }

    /** @return HasMany<OperationalStop, $this> */
    public function operationalStops(): HasMany
    {
        return $this->hasMany(OperationalStop::class);
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

    /** D9: a court is addressed by its id only; a former slug URL is a 404, not a database error. */
    public function resolveRouteBinding($value, $field = null)
    {
        // (int) caps an id past bigint at PHP_INT_MAX, so it finds nothing instead of a PostgreSQL range error (500).
        return ctype_digit((string) $value) ? $this->whereKey((int) $value)->first() : null;
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

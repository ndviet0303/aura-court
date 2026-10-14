<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

#[Fillable(['organization_id', 'name', 'slug', 'address', 'city', 'district', 'ward', 'phone', 'email', 'description', 'images', 'google_maps_url', 'latitude', 'longitude', 'amenities', 'is_active', 'status', 'status_reason', 'published_at', 'approved_public_snapshot', 'current_publication_revision_id', 'current_receiving_connection_id'])]
class Venue extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $appends = ['share_url', 'timezone'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'amenities' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'published_at' => 'datetime',
            'approved_public_snapshot' => 'array',
            'images' => 'array',
        ];
    }

    public function getIsActiveAttribute(): bool
    {
        if (isset($this->attributes['status'])) {
            return in_array($this->attributes['status'], ['published', 'active'], true);
        }

        return (bool) ($this->attributes['is_active'] ?? false);
    }

    public function setIsActiveAttribute($value): void
    {
        $this->attributes['is_active'] = (bool) $value;
        if ($value && (! isset($this->attributes['status']) || $this->attributes['status'] === 'temporarily_closed')) {
            $this->attributes['status'] = 'published';
        } elseif (! $value && in_array($this->attributes['status'] ?? null, ['published', 'active'], true)) {
            $this->attributes['status'] = 'temporarily_closed';
        }
    }

    public function getAmenitiesAttribute($value): array
    {
        if ($value !== null) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($decoded) && count($decoded) > 0) {
                return $decoded;
            }
        }

        return ['parking', 'wifi', 'shower', 'cafe', 'shop', 'lounge'];
    }

    public function getTimezoneAttribute(): string
    {
        return $this->attributes['timezone']
            ?? ($this->relationLoaded('organization') ? $this->organization?->timezone : null)
            ?? 'Asia/Ho_Chi_Minh';
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function sportZones(): HasMany
    {
        return $this->hasMany(SportZone::class);
    }

    public function publicationRevisions(): HasMany
    {
        return $this->hasMany(VenuePublicationRevision::class)->orderBy('revision_number', 'desc');
    }

    public function currentPublicationRevision(): BelongsTo
    {
        return $this->belongsTo(VenuePublicationRevision::class, 'current_publication_revision_id');
    }

    public function bookingPolicyVersions(): HasMany
    {
        return $this->hasMany(VenueBookingPolicy::class)->orderByDesc('version');
    }

    /** @return HasOne<VenueBookingPolicy, $this> */
    public function currentBookingPolicy(): HasOne
    {
        return $this->hasOne(VenueBookingPolicy::class)->latestOfMany('version');
    }

    /** @return BelongsTo<ReceivingConnection, $this> */
    public function currentReceivingConnection(): BelongsTo
    {
        return $this->belongsTo(ReceivingConnection::class, 'current_receiving_connection_id');
    }

    public function isPublished(): bool
    {
        return in_array($this->status ?? 'draft', ['published', 'active'], true);
    }

    public function isDraft(): bool
    {
        return ($this->status ?? 'draft') === 'draft';
    }

    public function isPendingPublication(): bool
    {
        return $this->status === 'pending_publication';
    }

    public function isRevisionRequired(): bool
    {
        return $this->status === 'revision_required';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTemporarilyClosed(): bool
    {
        return $this->status === 'temporarily_closed';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function getShareUrlAttribute(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/venues/'.$this->slug.'/directions';
    }

    public function toArray()
    {
        $attributes = parent::toArray();
        $publicSnapshot = request()
            && request()->is('api/venues', 'api/venues/*', 'api/*/venues', 'api/*/venues/*')
            && $this->isPublished()
            && is_array($this->approved_public_snapshot['venue'] ?? null)
                ? $this->approved_public_snapshot['venue']
                : null;

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

        if ($publicSnapshot !== null) {
            $attributes = array_merge($attributes, $publicSnapshot);
        }

        return $attributes;
    }
}

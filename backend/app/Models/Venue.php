<?php

namespace App\Models;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

#[Fillable(['organization_id', 'name', 'slug', 'address', 'city', 'district', 'ward', 'phone', 'email', 'description', 'images', 'google_maps_url', 'latitude', 'longitude', 'amenities', 'status', 'status_reason', 'published_at', 'current_receiving_connection_id'])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory, HasTranslations;

    /** D3: shared with courts. Suspension is a separate record, see activeSuspension(). */
    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_INACTIVE, self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_CLOSED];

    public array $translatable = ['name'];

    protected $appends = ['share_url', 'timezone'];

    /** D1: no approved public version any more; the columns stay only until the contract migration drops them. */
    protected $hidden = ['approved_public_snapshot', 'current_publication_revision_id'];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'published_at' => 'datetime',
            'images' => 'array',
        ];
    }

    // ponytail: read-only mirror of status for frontend views that still read is_active; drop it with the is_active column once they read status.
    public function getIsActiveAttribute(): bool
    {
        return ($this->attributes['status'] ?? null) === self::STATUS_ACTIVE;
    }

    /** BR-26: only what the owner declared; no saved amenities means none, never a default list. */
    public function getAmenitiesAttribute($value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
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

    /** @return HasMany<PriceTable, $this> */
    public function priceTables(): HasMany
    {
        return $this->hasMany(PriceTable::class);
    }

    // ponytail: only the legacy partner:backfill-mappings / partner:reconcile-legacy commands still read revisions; drop with venue_publication_revisions in the contract migration.
    public function publicationRevisions(): HasMany
    {
        return $this->hasMany(VenuePublicationRevision::class)->orderBy('revision_number', 'desc');
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

    /** @return HasOne<VenuePackage, $this> */
    public function currentPackage(): HasOne
    {
        return $this->hasOne(VenuePackage::class)->latestOfMany();
    }

    /** @return HasMany<VenuePackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(VenuePackage::class);
    }

    /** @return HasMany<PackagePaymentRequest, $this> */
    public function packagePaymentRequests(): HasMany
    {
        return $this->hasMany(PackagePaymentRequest::class);
    }

    /** @return HasMany<VenueSuspension, $this> */
    public function suspensions(): HasMany
    {
        return $this->hasMany(VenueSuspension::class);
    }

    /** @return HasOne<VenueSuspension, $this> */
    public function activeSuspension(): HasOne
    {
        return $this->hasOne(VenueSuspension::class)->whereNull('lifted_at');
    }

    /** @return HasMany<OperationalStop, $this> */
    public function operationalStops(): HasMany
    {
        return $this->hasMany(OperationalStop::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** Always a fresh query, so a check under a row lock sees a suspension committed meanwhile. */
    public function isSuspended(): bool
    {
        return $this->activeSuspension()->exists();
    }

    public function getShareUrlAttribute(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/venues/'.$this->slug.'/directions';
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

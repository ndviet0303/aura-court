<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tax_code',
        'status',
        'partner_status',
        'partner_status_reason',
        'partner_status_changed_at',
        'source_kyc_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'partner_status_changed_at' => 'datetime',
    ];

    protected $appends = [
        'support_email',
        'phone',
        'address',
        'brand_color',
        'logo_url',
        'auto_approve_booking',
        'resale_fee_percent',
        'currency',
        'timezone',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::increment('courts_version');
            Cache::increment('venues_version');
        });
    }

    public function getSupportEmailAttribute(): ?string
    {
        return $this->settings['support_email'] ?? null;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->settings['phone'] ?? null;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->settings['address'] ?? null;
    }

    public function getBrandColorAttribute(): ?string
    {
        return $this->settings['brand_color'] ?? '#6B2D3F';
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->settings['logo_url'] ?? null;
    }

    public function getAutoApproveBookingAttribute(): bool
    {
        return (bool) ($this->settings['auto_approve_booking'] ?? true);
    }

    public function getResaleFeePercentAttribute(): float
    {
        return (float) ($this->settings['resale_fee_percent'] ?? 5);
    }

    public function getCurrencyAttribute(): string
    {
        return (string) ($this->settings['currency'] ?? 'VND');
    }

    public function getTimezoneAttribute(): string
    {
        return (string) ($this->settings['timezone'] ?? 'Asia/Ho_Chi_Minh');
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function courts()
    {
        return $this->hasManyThrough(Court::class, Venue::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(TenantCustomer::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot(['role', 'permissions', 'status'])
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->hasOneThrough(
            User::class,
            OrganizationMember::class,
            'organization_id',
            'id',
            'id',
            'user_id'
        )->where('organization_members.role', OrganizationMember::ROLE_ORGANIZATION_OWNER);
    }

    public function hasMember(int $userId, ?string $role = null): bool
    {
        $query = $this->members()->where('user_id', $userId)->where('status', 'active');
        if ($role) {
            $query->where('role', $role);
        }

        return $query->exists();
    }

    public function sourceKyc(): BelongsTo
    {
        return $this->belongsTo(HostKyc::class, 'source_kyc_id');
    }

    public function isApprovedPartner(): bool
    {
        return $this->partner_status === 'approved';
    }

    public function isSuspendedPartner(): bool
    {
        return $this->partner_status === 'suspended';
    }

    public function isTerminatedPartner(): bool
    {
        return $this->partner_status === 'terminated';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PlatformSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(PlatformSubscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->latestOfMany();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription !== null && $this->activeSubscription->isValid();
    }
}

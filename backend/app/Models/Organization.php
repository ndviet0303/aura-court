<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tax_code',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $appends = [
        'support_email',
        'phone',
        'address',
        'brand_color',
        'logo_url',
        'auto_approve_booking',
        'resale_fee_percent',
        'cancellation_grace_hours',
        'currency',
        'timezone',
    ];

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

    public function getCancellationGraceHoursAttribute(): int
    {
        return (int) ($this->settings['cancellation_grace_hours'] ?? 12);
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot(['role', 'permissions', 'status'])
            ->withTimestamps();
    }

    public function hasMember(int $userId, ?string $role = null): bool
    {
        $query = $this->members()->where('user_id', $userId)->where('status', 'active');
        if ($role) {
            $query->where('role', $role);
        }
        return $query->exists();
    }
}

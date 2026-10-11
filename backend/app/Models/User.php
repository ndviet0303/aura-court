<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'phone', 'phone_verified_at', 'play_level', 'favorite_sports', 'avatar_url', 'onboarded', 'password_set', 'social_provider'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarded' => 'boolean',
            'password_set' => 'boolean',
            'favorite_sports' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRestricted(): bool
    {
        return in_array($this->status, ['suspended', 'banned', 'locked'], true);
    }

    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === 'platform_admin' || $this->role === 'admin';
    }

    public function isDemo(): bool
    {
        return str_ends_with(strtolower($this->email), '@demo.auracourt.vn');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function organizationMembers(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['role', 'permissions', 'status'])
            ->withTimestamps();
    }

    public function roleInOrganization(int $organizationId): ?string
    {
        $member = $this->organizationMembers()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->first();

        return $member?->role;
    }

    public function hasRoleInOrganization(int $organizationId, string|array $roles): bool
    {
        $roles = (array) $roles;
        $currentRole = $this->roleInOrganization($organizationId);

        return $currentRole !== null && in_array($currentRole, $roles, true);
    }

    public function isOrganizationOwner(?int $organizationId = null): bool
    {
        $query = $this->organizationMembers()
            ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)
            ->where('status', 'active');
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->exists();
    }

    public function isVenueManager(?int $organizationId = null): bool
    {
        $query = $this->organizationMembers()
            ->where('role', OrganizationMember::ROLE_VENUE_MANAGER)
            ->where('status', 'active');
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->exists();
    }

    public function isStaffOrCashier(?int $organizationId = null): bool
    {
        $query = $this->organizationMembers()
            ->whereIn('role', [OrganizationMember::ROLE_STAFF, OrganizationMember::ROLE_CASHIER])
            ->where('status', 'active');
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->exists();
    }

    public function getAuthorizedOrganizationIds(): array
    {
        if ($this->isPlatformAdmin()) {
            return Organization::pluck('id')->toArray();
        }

        return $this->organizationMembers()
            ->where('status', 'active')
            ->whereIn('role', [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_VENUE_MANAGER,
                OrganizationMember::ROLE_STAFF,
                OrganizationMember::ROLE_CASHIER,
            ])
            ->pluck('organization_id')
            ->unique()
            ->toArray();
    }

    public function getAuthorizedVenueIds(?int $organizationId = null): ?array
    {
        if ($this->isPlatformAdmin()) {
            return null;
        }

        $query = $this->organizationMembers()
            ->where('status', 'active')
            ->whereIn('role', [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_VENUE_MANAGER,
                OrganizationMember::ROLE_STAFF,
            ]);
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $memberships = $query->get();
        if ($memberships->isEmpty()) {
            return [];
        }

        $isFullOwner = $memberships->contains(fn ($m) => $m->role === OrganizationMember::ROLE_ORGANIZATION_OWNER);
        if ($isFullOwner && ! $organizationId) {
            $ownedOrgIds = $memberships->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->pluck('organization_id');
            $allVenueIds = Venue::whereIn('organization_id', $ownedOrgIds)->pluck('id')->toArray();

            foreach ($memberships->where('role', '!=', OrganizationMember::ROLE_ORGANIZATION_OWNER) as $m) {
                $perms = $m->permissions ?? [];
                if (! empty($perms['venue_ids'])) {
                    $allVenueIds = array_merge($allVenueIds, (array) $perms['venue_ids']);
                } elseif (! empty($perms['venue_id'])) {
                    $allVenueIds[] = (int) $perms['venue_id'];
                } else {
                    $orgVenues = Venue::where('organization_id', $m->organization_id)->pluck('id')->toArray();
                    $allVenueIds = array_merge($allVenueIds, $orgVenues);
                }
            }

            return array_values(array_unique($allVenueIds));
        }

        if ($isFullOwner && $organizationId) {
            return Venue::where('organization_id', $organizationId)->pluck('id')->toArray();
        }

        $venueIds = [];
        foreach ($memberships as $m) {
            $perms = $m->permissions ?? [];
            if (! empty($perms['venue_ids'])) {
                $venueIds = array_merge($venueIds, (array) $perms['venue_ids']);
            } elseif (! empty($perms['venue_id'])) {
                $venueIds[] = (int) $perms['venue_id'];
            } else {
                $orgVenues = Venue::where('organization_id', $m->organization_id)->pluck('id')->toArray();
                $venueIds = array_merge($venueIds, $orgVenues);
            }
        }

        return array_values(array_unique($venueIds));
    }

    public function canManageVenue(int|Venue $venue): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        $venueModel = is_int($venue) ? Venue::find($venue) : $venue;
        if (! $venueModel) {
            return false;
        }

        if ($venueModel->organization_id && $this->hasRoleInOrganization($venueModel->organization_id, [OrganizationMember::ROLE_ORGANIZATION_OWNER])) {
            return true;
        }

        $member = $this->organizationMembers()
            ->where('organization_id', $venueModel->organization_id)
            ->where('status', 'active')
            ->whereIn('role', [OrganizationMember::ROLE_VENUE_MANAGER, OrganizationMember::ROLE_STAFF])
            ->first();

        if (! $member) {
            return false;
        }

        $perms = $member->permissions ?? [];
        if (! empty($perms['venue_ids'])) {
            return in_array($venueModel->id, (array) $perms['venue_ids'], true);
        }
        if (! empty($perms['venue_id'])) {
            return (int) $perms['venue_id'] === $venueModel->id;
        }

        return true;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function tenantCustomers(): HasMany
    {
        return $this->hasMany(TenantCustomer::class);
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

<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'phone', 'phone_verified_at', 'play_level', 'favorite_sports', 'avatar_url', 'onboarded', 'password_set', 'social_provider'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const SOCIAL_PLACEHOLDER_EMAIL_DOMAIN = '@facebook.auracourt.vn';

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
            'sessions_revoked_at' => 'datetime',
        ];
    }

    /** BR-08: 10 digits starting with 0, or +84 followed by 9 digits. */
    public const PHONE_PATTERN = '/^(0\d{9}|\+84\d{9})$/';

    /**
     * BR-02: one account per email, compared trimmed and lower-cased.
     *
     * @return Attribute<string, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : Str::lower(trim($value)));
    }

    /** BR-08: a profile is complete with a 2–100 character name and a valid phone number. */
    public function hasCompleteProfile(): bool
    {
        $name = trim((string) $this->name);

        return mb_strlen($name) >= 2 && mb_strlen($name) <= 100 && preg_match(self::PHONE_PATTERN, (string) $this->phone) === 1;
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

    /** BR-06: a Facebook sign-up without an email holds an unreachable address until the user adds a real one. */
    public function needsEmail(): bool
    {
        return str_ends_with(strtolower($this->email), self::SOCIAL_PLACEHOLDER_EMAIL_DOMAIN);
    }

    public function sendEmailVerificationNotification(): void
    {
        if (! $this->needsEmail()) {
            $this->notify(new VerifyEmailNotification);
        }
    }

    /** @return HasMany<OrganizationMember, $this> */
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

    /** @return list<int> venues the user may operate */
    public function getAuthorizedVenueIds(?int $organizationId = null): array
    {
        $query = $this->organizationMembers()
            ->where('status', 'active')
            ->whereIn('role', [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
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
                $allVenueIds = array_merge($allVenueIds, $m->assignedVenueIds());
            }

            return array_values(array_unique($allVenueIds));
        }

        if ($isFullOwner && $organizationId) {
            return Venue::where('organization_id', $organizationId)->pluck('id')->toArray();
        }

        $venueIds = [];
        foreach ($memberships as $m) {
            $venueIds = array_merge($venueIds, $m->assignedVenueIds());
        }

        return array_values(array_unique($venueIds));
    }

    /** Venue management (settings, courts, prices, contracts) belongs to the organization owner. */
    public function canManageVenue(int|Venue $venue): bool
    {
        return $this->venueAccess($venue, []);
    }

    /** BR-21: daily operations (schedule, bookings, check-in/out, payments, court status) also open to staff assigned to the venue. */
    public function canOperateVenue(int|Venue $venue): bool
    {
        return $this->venueAccess($venue, [OrganizationMember::ROLE_STAFF]);
    }

    /**
     * BR-23: platform admin rights do not reach organization operations.
     *
     * @param  list<string>  $scopedRoles  roles that reach only the venues assigned to them
     */
    private function venueAccess(int|Venue $venue, array $scopedRoles): bool
    {
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
            ->whereIn('role', $scopedRoles)
            ->first();

        if (! $member) {
            return false;
        }

        return in_array($venueModel->id, $member->assignedVenueIds(), true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
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

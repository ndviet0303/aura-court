<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    use HasFactory;

    public const ROLE_ORGANIZATION_OWNER = 'organization_owner';

    public const ROLE_VENUE_MANAGER = 'venue_manager';

    public const ROLE_CASHIER = 'cashier';

    public const ROLE_STAFF = 'staff';

    public const ROLE_CUSTOMER = 'customer';

    public const VALID_ROLES = [
        self::ROLE_ORGANIZATION_OWNER,
        self::ROLE_VENUE_MANAGER,
        self::ROLE_CASHIER,
        self::ROLE_STAFF,
        self::ROLE_CUSTOMER,
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'permissions',
        'status',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    protected $appends = [
        'venue_id',
        'venue_ids',
    ];

    public function getVenueIdAttribute(): ?int
    {
        if (! is_array($this->permissions)) {
            return null;
        }

        $id = $this->permissions['venue_id'] ?? ($this->permissions['venue_ids'][0] ?? null);

        return is_numeric($id) ? (int) $id : null;
    }

    public function getVenueIdsAttribute(): array
    {
        if (! is_array($this->permissions)) {
            return [];
        }

        if (isset($this->permissions['venue_ids']) && is_array($this->permissions['venue_ids'])) {
            return array_values(array_map('intval', $this->permissions['venue_ids']));
        }

        if (isset($this->permissions['venue_id']) && is_numeric($this->permissions['venue_id'])) {
            return [(int) $this->permissions['venue_id']];
        }

        return [];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_ORGANIZATION_OWNER;
    }

    public function isManager(): bool
    {
        return in_array($this->role, [self::ROLE_ORGANIZATION_OWNER, self::ROLE_VENUE_MANAGER]);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_ORGANIZATION_OWNER, self::ROLE_VENUE_MANAGER, self::ROLE_CASHIER, self::ROLE_STAFF]);
    }
}

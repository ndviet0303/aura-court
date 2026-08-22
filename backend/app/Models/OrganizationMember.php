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

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

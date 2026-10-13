<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'organization_id', 'venue_id', 'created_by', 'name', 'format', 'status', 'version', 'starts_at', 'settings'])]
class Tournament extends Model
{
    use BelongsToTenant;

    protected $appends = ['entry_fee', 'max_participants'];

    // Null when no fee was published; registration then charges nothing.
    public function getEntryFeeAttribute(): ?string
    {
        return isset($this->settings['entry_fee']) ? (string) $this->settings['entry_fee'] : null;
    }

    public function getMaxParticipantsAttribute(): int
    {
        return (int) ($this->settings['max_participants'] ?? 16);
    }

    protected static function booted(): void
    {
        static::creating(function (Tournament $tournament) {
            if (empty($tournament->uuid)) {
                $tournament->uuid = (string) Str::uuid7();
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (Str::isUuid((string) $value)) {
            return $this->where('uuid', (string) $value)->first();
        }

        if (is_numeric($value)) {
            return $this->where('id', (int) $value)->first();
        }

        return $this->where('uuid', $value)->first();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'starts_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(TournamentFixture::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TournamentEvent::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(TournamentGrant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAuthorizedOfficial(User $user, ?string $requiredRole = null): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($user->hasRoleInOrganization($this->organization_id, ['organization_owner', 'venue_manager', 'staff'])) {
            return true;
        }

        return $this->grants()
            ->active()
            ->where('user_id', $user->id)
            ->when($requiredRole, function ($q) use ($requiredRole) {
                if ($requiredRole === 'official') {
                    return $q->whereIn('role', ['organizer', 'official']);
                }

                return $q->where('role', $requiredRole);
            })
            ->exists();
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'organization_id', 'created_by', 'name', 'format', 'status', 'version', 'starts_at', 'settings'])]
class Tournament extends Model
{
    use BelongsToTenant;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

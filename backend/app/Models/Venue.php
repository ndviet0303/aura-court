<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'slug', 'address', 'google_maps_url', 'is_active'])]
class Venue extends Model
{
    protected $appends = ['share_url'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function sportZones(): HasMany
    {
        return $this->hasMany(SportZone::class);
    }

    public function getShareUrlAttribute(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/venues/'.$this->slug.'/directions';
    }
}


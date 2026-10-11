<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'skill_level', 'city', 'approximate_latitude', 'approximate_longitude', 'location_precision_km', 'availability', 'preferred_positions', 'reputation_score', 'visibility'])]
class PlayerProfile extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['skill_level' => 'integer', 'approximate_latitude' => 'float', 'approximate_longitude' => 'float', 'availability' => 'array', 'preferred_positions' => 'array', 'reputation_score' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'actor_id', 'target_type', 'target_id', 'action', 'reason', 'metadata'])]
class ModerationRecord extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Moderation records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Moderation records are immutable.'));
    }
}

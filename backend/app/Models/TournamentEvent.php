<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'tournament_id', 'aggregate_version', 'event_type', 'created_by', 'payload'])]
class TournamentEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['aggregate_version' => 'integer', 'payload' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Tournament events are immutable.'));
        static::deleting(fn () => throw new \LogicException('Tournament events are immutable.'));
    }
}

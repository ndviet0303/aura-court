<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FR-132: what the platform admin decided on reported content, and why; never changed afterwards. */
#[Fillable(['actor_id', 'target_type', 'target_id', 'action', 'reason', 'metadata'])]
class ModerationRecord extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Moderation records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Moderation records are immutable.'));
    }
}

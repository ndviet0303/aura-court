<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'message_key', 'channel', 'event_type', 'aggregate_type', 'aggregate_id', 'payload', 'status', 'attempts', 'available_at', 'locked_at', 'published_at', 'last_error'])]
class OutboxMessage extends Model
{
    use BelongsToTenant;

    protected function casts(): array { return ['payload' => 'array', 'available_at' => 'datetime', 'locked_at' => 'datetime', 'published_at' => 'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'user_id', 'qualifying_activity_id', 'filed_by', 'decided_by', 'status', 'decision', 'reason', 'resolution', 'decided_at'])]
class ReputationDispute extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}

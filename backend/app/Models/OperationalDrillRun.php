<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['drill_type', 'status', 'evidence', 'failure_reason', 'started_at', 'completed_at'])]
class OperationalDrillRun extends Model
{
    protected function casts(): array
    {
        return ['evidence' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}

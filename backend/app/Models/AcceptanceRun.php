<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phase', 'status', 'suites', 'results', 'started_at', 'completed_at'])]
class AcceptanceRun extends Model
{
    protected function casts(): array { return ['suites' => 'array', 'results' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime']; }
}

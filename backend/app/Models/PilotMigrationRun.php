<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mode', 'status', 'organization_ids', 'source_booking_count', 'target_reservation_count', 'source_amount', 'target_amount', 'source_checksum', 'target_checksum', 'results', 'failure_reason', 'started_at', 'completed_at'])]
class PilotMigrationRun extends Model
{
    protected function casts(): array
    {
        return ['organization_ids' => 'array', 'results' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}

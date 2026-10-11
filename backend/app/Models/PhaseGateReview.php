<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phase', 'decision', 'organization_ids', 'reconciliation_run_ids', 'rollback_verified', 'evidence', 'reviewer', 'failure_reason', 'reviewed_at'])]
class PhaseGateReview extends Model
{
    protected function casts(): array
    {
        return ['organization_ids' => 'array', 'reconciliation_run_ids' => 'array', 'rollback_verified' => 'boolean', 'evidence' => 'array', 'reviewed_at' => 'datetime'];
    }
}

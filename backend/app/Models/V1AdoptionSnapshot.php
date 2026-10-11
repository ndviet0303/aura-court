<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['period_start', 'period_end', 'legacy_booking_count', 'v2_reservation_count', 'legacy_active_count', 'legacy_user_count', 'evidence'])]
class V1AdoptionSnapshot extends Model
{
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'evidence' => 'array'];
    }
}

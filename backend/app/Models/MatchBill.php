<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'match_post_id',
    'actual_headcount',
    'court_duration_minutes',
    'court_hourly_rate',
    'shuttlecock_count',
    'shuttlecock_unit_price',
    'court_subtotal',
    'shuttlecock_subtotal',
    'total_amount',
    'per_person_amount',
    'is_approximate',
    'status',
    'version',
    'finalized_by',
    'finalized_at',
    'snapshot',
])]
class MatchBill extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    protected function casts(): array
    {
        return [
            'actual_headcount' => 'integer',
            'court_duration_minutes' => 'integer',
            'court_hourly_rate' => 'integer',
            'shuttlecock_count' => 'integer',
            'shuttlecock_unit_price' => 'integer',
            'court_subtotal' => 'integer',
            'shuttlecock_subtotal' => 'integer',
            'total_amount' => 'integer',
            'per_person_amount' => 'integer',
            'is_approximate' => 'boolean',
            'version' => 'integer',
            'finalized_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function matchPost(): BelongsTo
    {
        return $this->belongsTo(MatchPost::class, 'match_post_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}

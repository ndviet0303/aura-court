<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'match_post_id',
    'user_id',
    'status',
    'joined_at',
    'verified_at',
    'attendance_status',
    'attendance_recorded_by',
    'attendance_recorded_at',
    'attendance_notes',
])]
class MatchParticipation extends Model
{
    use BelongsToTenant;

    public const STATUS_JOINED = 'joined';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const ATTENDANCE_PRESENT = 'present';

    public const ATTENDANCE_LATE = 'late';

    public const ATTENDANCE_ABSENT = 'absent';

    public const ATTENDANCE_WITHDRAWN = 'withdrawn';

    public const VALID_ATTENDANCE_STATUSES = [
        self::ATTENDANCE_PRESENT,
        self::ATTENDANCE_LATE,
        self::ATTENDANCE_ABSENT,
        self::ATTENDANCE_WITHDRAWN,
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'verified_at' => 'datetime',
            'attendance_recorded_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(MatchPost::class, 'match_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_recorded_by');
    }
}

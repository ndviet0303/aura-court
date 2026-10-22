<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One handling case per affected booking and source (design D7, D8). Unique (reservation_id, source_type,
 * source_id), so saving the same change twice opens no second case. source_id is the id of the source row
 * (stop, suspension, schedule version or exception) and has no foreign key. source_starts_at / source_ends_at
 * snapshot the source window for the case list (UTC; for a stop its start and expected or recorded end).
 */
#[Fillable(['reservation_id', 'venue_id', 'court_id', 'source_type', 'source_id', 'source_starts_at', 'source_ends_at', 'status', 'contact_channel', 'note', 'refund_note', 'handled_by', 'handled_at'])]
class AffectedReservationCase extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONTACTING = 'contacting';

    public const STATUS_UNREACHABLE = 'unreachable';

    public const STATUS_MOVED = 'moved';

    public const STATUS_TIME_CHANGED = 'time_changed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_KEPT = 'kept';

    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_CONTACTING, self::STATUS_UNREACHABLE];

    public const CLOSED_STATUSES = [self::STATUS_MOVED, self::STATUS_TIME_CHANGED, self::STATUS_CANCELLED, self::STATUS_KEPT];

    public const SOURCE_OPERATIONAL_STOP = 'operational_stop';

    public const SOURCE_VENUE_SUSPENSION = 'venue_suspension';

    /** Weekly venue operating hours or closed days. */
    public const SOURCE_VENUE_SCHEDULE = 'venue_schedule';

    /** A dated venue or court exception, created, changed or deleted. */
    public const SOURCE_SCHEDULE_EXCEPTION = 'schedule_exception';

    public const SOURCE_COURT_SCHEDULE = 'court_schedule';

    public const SOURCE_TYPES = [
        self::SOURCE_OPERATIONAL_STOP,
        self::SOURCE_VENUE_SUSPENSION,
        self::SOURCE_VENUE_SCHEDULE,
        self::SOURCE_SCHEDULE_EXCEPTION,
        self::SOURCE_COURT_SCHEDULE,
    ];

    public const CONTACT_CHANNELS = ['phone', 'zalo', 'chat'];

    protected function casts(): array
    {
        return [
            'source_starts_at' => UtcDateTime::class,
            'source_ends_at' => UtcDateTime::class,
            'handled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** The source stop; read only when source_type is operational_stop. @return BelongsTo<OperationalStop, $this> */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(OperationalStop::class, 'source_id');
    }

    /** The source suspension; read only when source_type is venue_suspension. @return BelongsTo<VenueSuspension, $this> */
    public function suspension(): BelongsTo
    {
        return $this->belongsTo(VenueSuspension::class, 'source_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}

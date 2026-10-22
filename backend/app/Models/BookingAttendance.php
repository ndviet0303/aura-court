<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'reservation_id',
    'legacy_booking_id',
    'status',
    'checked_in_at',
    'checked_in_by',
    'checked_out_at',
    'checked_out_by',
    'no_show_at',
    'no_show_by',
    'notes',
    'metadata',
])]
class BookingAttendance extends Model
{
    use BelongsToTenant;

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_CHECKED_OUT = 'checked_out';

    public const STATUS_NO_SHOW = 'no_show';

    /** Module 06 D3: the status a booking shows for its attendance row; a booking without one shows confirmed. */
    public const OPERATIONAL_STATUS = [
        self::STATUS_CHECKED_IN => 'in_use',
        self::STATUS_CHECKED_OUT => 'completed',
        self::STATUS_NO_SHOW => 'no_show',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'no_show_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function legacyBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'legacy_booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    /** @return BelongsTo<User, $this> */
    public function noShowBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_show_by');
    }
}

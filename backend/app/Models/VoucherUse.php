<?php

namespace App\Models;

use App\Casts\AppTimezoneDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One booking's use of a program (design D11): held while the booking is pending payment, then used at confirmation
 * or released once. A program's held and used counts are counted from these rows.
 */
#[Fillable(['reservation_id', 'program_id', 'voucher_code_id', 'status', 'discount', 'held_at', 'used_at', 'released_at'])]
class VoucherUse extends Model
{
    public const STATUS_HELD = 'held';

    public const STATUS_USED = 'used';

    public const STATUS_RELEASED = 'released';

    protected function casts(): array
    {
        return [
            'discount' => 'integer',
            'held_at' => AppTimezoneDateTime::class,
            'used_at' => AppTimezoneDateTime::class,
            'released_at' => AppTimezoneDateTime::class,
        ];
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<PromotionProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PromotionProgram::class, 'program_id');
    }

    /** @return BelongsTo<VoucherCode, $this> */
    public function voucherCode(): BelongsTo
    {
        return $this->belongsTo(VoucherCode::class);
    }
}

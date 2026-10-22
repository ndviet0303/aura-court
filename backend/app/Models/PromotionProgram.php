<?php

namespace App\Models;

use App\Casts\AppTimezoneDateTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A venue's promotion program (design D7, D8): issued when created, usable in [starts_at, ends_at) until stopped.
 * Its voucher kind, discount kind, codes and code count never change (D14); a percentage is percent_bp hundredths
 * of a percent (D27). Programs are never deleted.
 */
#[Fillable(['venue_id', 'name', 'type', 'discount_kind', 'percent_bp', 'amount', 'max_discount', 'min_rental', 'starts_at', 'ends_at', 'max_uses', 'code_count', 'stopped_at'])]
class PromotionProgram extends Model
{
    public const TYPE_PUBLIC = 'public';

    public const TYPE_PRIVATE = 'private';

    public const KIND_PERCENT = 'percent';

    public const KIND_FIXED = 'fixed';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_RUNNING = 'running';

    public const STATUS_ENDED = 'ended';

    public const STATUS_STOPPED = 'stopped';

    /** The columns that decide whether and how much a pending booking is discounted (D19); the court set counts too. */
    public const PRICING_TERMS = ['percent_bp', 'amount', 'max_discount', 'min_rental', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'percent_bp' => 'integer',
            'amount' => 'integer',
            'max_discount' => 'integer',
            'min_rental' => 'integer',
            'max_uses' => 'integer',
            'code_count' => 'integer',
            'starts_at' => AppTimezoneDateTime::class,
            'ends_at' => AppTimezoneDateTime::class,
            'stopped_at' => AppTimezoneDateTime::class,
        ];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsToMany<Court, $this> */
    public function courts(): BelongsToMany
    {
        return $this->belongsToMany(Court::class, 'promotion_program_courts', 'program_id', 'court_id');
    }

    /** @return HasMany<VoucherCode, $this> */
    public function codes(): HasMany
    {
        return $this->hasMany(VoucherCode::class, 'program_id');
    }

    /** @return HasMany<VoucherUse, $this> */
    public function uses(): HasMany
    {
        return $this->hasMany(VoucherUse::class, 'program_id');
    }

    /** Not named status(): Eloquent would read $program->status as a relation. */
    public function statusAt(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            $this->stopped_at !== null => self::STATUS_STOPPED,
            $at->lt($this->starts_at) => self::STATUS_UPCOMING,
            $at->lt($this->ends_at) => self::STATUS_RUNNING,
            default => self::STATUS_ENDED,
        };
    }
}

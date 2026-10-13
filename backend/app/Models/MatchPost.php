<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'creator_id',
    'court_id',
    'title',
    'visibility',
    'is_owner_hosted',
    'category',
    'skill_levels',
    'starts_at',
    'ends_at',
    'city',
    'approximate_latitude',
    'approximate_longitude',
    'min_skill',
    'max_skill',
    'required_participants',
    'joined_count',
    'status',
    'availability',
    'metadata',
    'registration_deadline',
    'reservation_id',
    'entitlement_id',
    'approval_required',
    'roster_closed_at',
])]
class MatchPost extends Model
{
    use BelongsToTenant;

    public const CATEGORY_GIAO_LUU = 'giao lưu';

    public const CATEGORY_VANG_LAI = 'đánh vãng lai';

    public const VALID_CATEGORIES = [
        self::CATEGORY_GIAO_LUU,
        self::CATEGORY_VANG_LAI,
        'giao_luu',
        'danh_vang_lai',
    ];

    public const SKILL_NEWBIE = 'newbie';

    public const SKILL_Y = 'Y';

    public const SKILL_TBY = 'TBY';

    public const SKILL_TB = 'TB';

    public const SKILL_K = 'K';

    public const VALID_SKILL_LABELS = [
        self::SKILL_NEWBIE,
        self::SKILL_Y,
        self::SKILL_TBY,
        self::SKILL_TB,
        self::SKILL_K,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'approximate_latitude' => 'float',
            'approximate_longitude' => 'float',
            'availability' => 'array',
            'metadata' => 'array',
            'is_owner_hosted' => 'boolean',
            'skill_levels' => 'array',
            'approval_required' => 'boolean',
            'registration_deadline' => UtcDateTime::class,
            'roster_closed_at' => UtcDateTime::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MatchInvitation::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(MatchParticipation::class);
    }

    public function bill(): HasOne
    {
        return $this->hasOne(MatchBill::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(ReservationEntitlement::class, 'entitlement_id');
    }

    public function isOwnerHosted(): bool
    {
        return (bool) ($this->is_owner_hosted || ($this->metadata['is_owner_hosted'] ?? false));
    }

    public function hasEnded(): bool
    {
        return in_array($this->status, ['ended', 'completed'], true);
    }

    public function isRosterClosed(): bool
    {
        return ! is_null($this->roster_closed_at);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'discount_type', 'discount_value', 'max_discount', 'min_spend', 'usage_limit', 'usage_count', 'starts_at', 'ends_at', 'is_active', 'can_combine_with_membership', 'metadata', 'organization_id', 'venue_id', 'court_id', 'promotion_campaign_id', 'status', 'eligibility_group', 'version', 'created_by', 'updated_by', 'activated_at', 'suspended_at', 'retired_at'])]
class Coupon extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_RETIRED = 'retired';

    public const GROUP_ALL = 'all_customers';

    public const GROUP_FIRST_TIME = 'first_time_customers';

    public const GROUP_RETURNING = 'returning_customers';

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'max_discount' => 'integer',
            'min_spend' => 'integer',
            'usage_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'can_combine_with_membership' => 'boolean',
            'metadata' => 'array',
            'version' => 'integer',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionCampaign::class, 'promotion_campaign_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}

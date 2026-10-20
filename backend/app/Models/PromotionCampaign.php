<?php

namespace App\Models;

use App\Casts\AppTimezoneDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'venue_id', 'court_id', 'name', 'description', 'status', 'eligibility_group', 'starts_at', 'ends_at', 'usage_limit', 'usage_count', 'min_spend', 'can_combine_with_membership', 'version', 'created_by', 'updated_by', 'activated_at', 'suspended_at', 'retired_at'])]
class PromotionCampaign extends Model
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
            'starts_at' => AppTimezoneDateTime::class,
            'ends_at' => AppTimezoneDateTime::class,
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'min_spend' => 'integer',
            'can_combine_with_membership' => 'boolean',
            'version' => 'integer',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'promotion_campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

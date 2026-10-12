<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'user_id', 'plan_id', 'plan_version', 'status', 'starts_at', 'ends_at'])]
class TenantMembership extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['plan_version' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<TenantMembershipPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TenantMembershipPlan::class, 'plan_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(MembershipEntitlement::class, 'membership_id');
    }
}

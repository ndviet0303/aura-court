<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'membership_id', 'benefit_code', 'benefit_value', 'starts_at', 'ends_at'])]
class MembershipEntitlement extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['benefit_value' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'membership_id');
    }
}

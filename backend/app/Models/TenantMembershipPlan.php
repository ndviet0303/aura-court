<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'version', 'price', 'benefits', 'status', 'published_at'])]
class TenantMembershipPlan extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['version' => 'integer', 'price' => 'integer', 'benefits' => 'array', 'published_at' => 'datetime'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'plan_id');
    }
}

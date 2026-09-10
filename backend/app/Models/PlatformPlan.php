<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformPlan extends Model
{
    use HasFactory;

    protected $table = 'platform_plans';

    protected $fillable = [
        'code',
        'name',
        'version',
        'price',
        'currency',
        'billing_cycle',
        'trial_days',
        'trial_enabled',
        'max_venues',
        'max_courts',
        'max_staff',
        'features',
        'terms_version',
        'terms_text',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'version' => 'integer',
        'trial_days' => 'integer',
        'trial_enabled' => 'boolean',
        'max_venues' => 'integer',
        'max_courts' => 'integer',
        'max_staff' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PlatformSubscription::class, 'plan_id');
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}

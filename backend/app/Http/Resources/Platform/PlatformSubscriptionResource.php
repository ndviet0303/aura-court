<?php

namespace App\Http\Resources\Platform;

use App\Models\PlatformSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformSubscription
 */
class PlatformSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->plan;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'plan_id' => $this->plan_id,
            'plan_name' => $plan?->name,
            'plan_code' => $plan?->code,
            'plan_version' => (int) $this->plan_version,
            'status' => $this->status,
            'is_valid' => $this->isValid(),
            'is_trial' => $this->isTrial(),
            'price' => (int) $this->price,
            'currency' => $this->currency ?? 'VND',
            'billing_cycle' => $this->billing_cycle,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'trial_starts_at' => $this->trial_starts_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'trial_days_remaining' => $this->trial_ends_at && $this->trial_ends_at->isFuture()
                ? (int) ceil(now()->diffInSeconds($this->trial_ends_at) / 86400)
                : 0,
            'auto_renew' => (bool) $this->auto_renew,
            'accepted_terms_version' => $this->accepted_terms_version,
            'quotas' => [
                'max_venues' => (int) ($plan?->max_venues ?? 1),
                'max_courts' => (int) ($plan?->max_courts ?? 4),
                'max_staff' => (int) ($plan?->max_staff ?? 2),
            ],
            'features' => $plan?->features ?? [],
        ];
    }
}

<?php

namespace App\Http\Resources\Platform;

use App\Models\PlatformPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformPlan
 */
class PlatformPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'version' => (int) $this->version,
            'price' => (int) $this->price,
            'price_formatted' => number_format((int) $this->price, 0, ',', '.').'₫',
            'currency' => $this->currency ?? 'VND',
            'billing_cycle' => $this->billing_cycle,
            'billing_months' => $this->billingMonths(),
            'trial_days' => (int) $this->trial_days,
            'trial_enabled' => (bool) $this->trial_enabled,
            'quotas' => [
                'max_venues' => (int) $this->max_venues,
                'max_courts' => (int) $this->max_courts,
                'max_staff' => (int) $this->max_staff,
            ],
            'features' => $this->features ?? [],
            'terms' => [
                'version' => (string) $this->terms_version,
                'text' => $this->terms_text,
            ],
            'status' => (string) $this->status,
            'fee_disclosure' => [
                'tax_note' => 'Đã bao gồm thuế giá trị gia tăng (nếu có).',
                'payment_processing_fee' => 'Miễn phí giao dịch qua cổng thanh toán liên kết.',
                'refund_policy' => 'Có thể hủy gói vào cuối chu kỳ thanh toán.',
            ],
            'is_active' => (bool) $this->is_active,
            'published_at' => $this->published_at?->toIso8601String(),
            'retired_at' => $this->retired_at?->toIso8601String(),
        ];
    }
}

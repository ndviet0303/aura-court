<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:160',
            'code' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:1000',
            'price' => 'nullable|integer|min:0',
            'billingCycle' => 'nullable|in:monthly,quarterly,yearly',
            'courtDiscountPercent' => 'nullable|integer|min:0|max:100',
            'pointMultiplier' => 'nullable|numeric|min:1|max:20',
            'advanceBookingDays' => 'nullable|integer|min:1|max:90',
            'freeWeekendHours' => 'nullable|integer|min:0|max:100',
            'cancellationFeeWaived' => 'nullable|boolean',
            'isPopular' => 'nullable|boolean',
            'perks' => 'nullable|array',
            'perks.*' => 'string|max:255',
            'status' => 'nullable|string|in:active,published,archived,draft',
            'version' => 'nullable|string|max:20',
        ];
    }
}

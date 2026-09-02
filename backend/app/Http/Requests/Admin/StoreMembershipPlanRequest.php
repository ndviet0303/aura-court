<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:160',
            'code' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|integer|min:0',
            'billingCycle' => 'nullable|in:monthly,quarterly,yearly',
            'courtDiscountPercent' => 'nullable|integer|min:0|max:100',
            'pointMultiplier' => 'nullable|numeric|min:1|max:20',
            'advanceBookingDays' => 'nullable|integer|min:1|max:90',
            'freeWeekendHours' => 'nullable|integer|min:0|max:100',
            'cancellationFeeWaived' => 'nullable|boolean',
            'isPopular' => 'nullable|boolean',
            'perks' => 'nullable|array',
            'perks.*' => 'string|max:255',
        ];
    }
}

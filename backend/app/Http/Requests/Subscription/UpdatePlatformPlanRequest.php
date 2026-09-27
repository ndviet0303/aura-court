<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'code' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:120'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_cycle' => ['sometimes', 'string', 'in:monthly,six_months,yearly'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'trial_enabled' => ['sometimes', 'boolean'],
            'max_venues' => ['sometimes', 'integer', 'min:1'],
            'max_courts' => ['sometimes', 'integer', 'min:1'],
            'max_staff' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', 'max:64'],
            'terms_version' => ['sometimes', 'string', 'max:32'],
            'terms_text' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}

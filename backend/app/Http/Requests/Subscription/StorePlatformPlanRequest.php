<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class StorePlatformPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_cycle' => ['required', 'string', 'in:monthly,six_months,yearly'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'trial_enabled' => ['sometimes', 'boolean'],
            'max_venues' => ['sometimes', 'integer', 'min:1'],
            'max_courts' => ['sometimes', 'integer', 'min:1'],
            'max_staff' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', 'max:64'],
            'terms_version' => ['required', 'string', 'max:32'],
            'terms_text' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

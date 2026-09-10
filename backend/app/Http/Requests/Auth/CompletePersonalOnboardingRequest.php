<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompletePersonalOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'account_type' => ['required', 'string', 'in:customer,court_owner'],
            'phone' => ['nullable', 'string', 'max:30'],
            'play_level' => ['nullable', 'string', 'max:50'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'courts_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CompletePersonalOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Số điện thoại phải gồm 10 chữ số bắt đầu bằng 0 hoặc +84 kèm 9 chữ số.'];
    }

    public function rules(): array
    {
        return [
            'account_type' => ['required', 'string', 'in:customer,court_owner'],
            'phone' => ['nullable', 'string', 'regex:'.User::PHONE_PATTERN],
            'play_level' => ['nullable', 'string', 'max:50'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'courts_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

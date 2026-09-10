<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPartnerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isPlatformAdmin());
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,revision_required,reject'],
            'reason' => ['required_if:decision,revision_required,reject', 'nullable', 'string', 'min:5', 'max:1000'],
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
            'feedback_fields' => ['nullable', 'array'],
            'reapplication_allowed' => ['nullable', 'boolean'],
            'reapplication_cooloff_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}

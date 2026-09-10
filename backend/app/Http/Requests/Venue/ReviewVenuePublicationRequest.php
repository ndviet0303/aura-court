<?php

namespace App\Http\Requests\Venue;

use Illuminate\Foundation\Http\FormRequest;

class ReviewVenuePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,revision_required,reject'],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:decision,revision_required,reject'],
            'feedback_fields' => ['nullable', 'array'],
        ];
    }
}

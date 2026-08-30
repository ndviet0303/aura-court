<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewHostKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject,request_supplement'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
            'checklist_state' => ['nullable', 'array'],
        ];
    }
}

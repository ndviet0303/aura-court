<?php

namespace App\Http\Requests\Admin;

use App\Models\SuspensionRemediation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewVenueRemediationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['feedback' => is_string($this->input('feedback')) ? trim($this->input('feedback')) : $this->input('feedback')]);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(array_keys(SuspensionRemediation::DECISIONS))],
            'feedback' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['feedback.required' => 'Vui lòng nhập phản hồi cho chủ cơ sở.'];
    }
}

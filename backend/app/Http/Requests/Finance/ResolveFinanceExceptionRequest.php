<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ResolveFinanceExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('resolution')) {
            if ($this->has('note')) {
                $this->merge(['resolution' => $this->input('note')]);
            } elseif ($this->has('reason')) {
                $this->merge(['resolution' => $this->input('reason')]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'min:5', 'max:2000'],
            'action_type' => ['nullable', 'string', 'max:100'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolution.required' => 'Vui lòng nhập lý do giải trình kiểm toán.',
            'resolution.min' => 'Lý do giải trình kiểm toán phải có ít nhất 20 ký tự.',
            'resolution.max' => 'Lý do giải trình kiểm toán không được vượt quá 2000 ký tự.',
        ];
    }
}

<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class AutoResolveReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'run_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string', 'in:safe_only,all'],
            'notes' => ['nullable', 'string', 'max:500'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'correction_type' => ['nullable', 'string', 'max:100'],
            'correction_details' => ['nullable', 'array'],
        ];
    }
}

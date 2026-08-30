<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class TriggerReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'run_date' => ['nullable', 'date'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}

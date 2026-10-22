<?php

namespace App\Http\Requests\Admin;

use App\Models\SuspensionRemediation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListVenueRemediationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(SuspensionRemediation::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

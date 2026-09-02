<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMultiplierRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:160',
            'multiplier' => 'required|numeric|min:1|max:20',
            'tag' => 'nullable|string|max:80',
            'schedule' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'isActive' => 'nullable|boolean',
        ];
    }
}

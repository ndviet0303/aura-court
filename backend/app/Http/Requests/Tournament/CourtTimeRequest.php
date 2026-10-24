<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Foundation\Http\FormRequest;

/** A court and a time range, read in Vietnam time: a reserved block (UC-7.30) or a fixture's slot (UC-7.31). */
class CourtTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_id' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function messages(): array
    {
        return ['ends_at.after' => 'Giờ kết thúc phải sau giờ bắt đầu.'];
    }
}

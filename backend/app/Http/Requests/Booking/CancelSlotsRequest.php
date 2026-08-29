<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CancelSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'integer',
            'reason' => 'nullable|string|max:255',
        ];
    }
}

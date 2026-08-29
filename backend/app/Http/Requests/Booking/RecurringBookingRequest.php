<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class RecurringBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|integer|exists:courts,id',
            'slots' => 'required|array|min:1|max:12',
            'slots.*' => 'date',
            'weeks' => 'required|integer|min:2|max:12',
        ];
    }
}

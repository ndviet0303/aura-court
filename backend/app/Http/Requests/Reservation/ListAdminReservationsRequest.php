<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class ListAdminReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:held,confirmed,in_use,completed,cancelled,expired,disputed,legacy',
            'date' => 'nullable|date_format:Y-m-d',
            'venue_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
        ];
    }
}

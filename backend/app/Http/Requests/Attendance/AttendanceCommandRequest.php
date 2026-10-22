<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D7, D8: check-in, check-out and no-show take only the booking code, scanned (AURA_BOOKING_<code>) or typed. The
 * route's role middleware admits owners and staff; the action checks the venue (BR-119).
 */
class AttendanceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}

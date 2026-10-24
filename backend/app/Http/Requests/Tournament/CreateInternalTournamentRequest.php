<?php

namespace App\Http\Requests\Tournament;

use App\Domain\Tournament\Rules\SetupRules;
use Illuminate\Foundation\Http\FormRequest;

/** UC-7.17, D17: an internal tournament inside one renter's confirmed bookings, run by a representative account. */
class CreateInternalTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return SetupRules::rules(false) + [
            'reservation_codes' => ['required', 'array', 'min:1', 'max:50'],
            'reservation_codes.*' => ['required', 'string', 'max:40', 'distinct'],
            'representative_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return SetupRules::messages() + ['reservation_codes.required' => 'Nhập ít nhất một mã đơn đặt sân đã xác nhận.'];
    }
}

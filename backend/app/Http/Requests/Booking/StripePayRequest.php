<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StripePayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'card_number' => 'nullable|string',
            'card_expiry' => 'nullable|string',
            'card_cvc' => 'nullable|string',
            'card_name' => 'nullable|string',
        ];
    }
}

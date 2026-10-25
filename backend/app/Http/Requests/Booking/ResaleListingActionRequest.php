<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

/** The buyer cancels their own resale hold. */
class ResaleListingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

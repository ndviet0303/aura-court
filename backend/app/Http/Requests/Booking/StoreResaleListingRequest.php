<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreResaleListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The buyer pays the venue owner, so no seller account is asked for.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asking_price' => ['required', 'integer', 'min:0'],
        ];
    }
}

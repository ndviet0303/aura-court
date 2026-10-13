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
     * A paid listing must carry the seller's own receiving account; the buyer pays it directly.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asking_price' => ['required', 'integer', 'min:0'],
            'seller_receiver' => ['exclude_if:asking_price,0', 'required', 'array'],
            'seller_receiver.bank_code' => ['exclude_if:asking_price,0', 'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'seller_receiver.account_number' => ['exclude_if:asking_price,0', 'required', 'string', 'regex:/^\d{4,34}$/'],
            'seller_receiver.account_name' => ['exclude_if:asking_price,0', 'required', 'string', 'max:160'],
        ];
    }
}

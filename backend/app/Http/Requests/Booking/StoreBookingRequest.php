<?php

namespace App\Http\Requests\Booking;

use App\Domain\Booking\DTOs\CreateBookingData;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'coupon_code' => 'nullable|string|max:40',
        ];
    }

    public function toData(): CreateBookingData
    {
        return CreateBookingData::fromArray($this->user()->id, $this->validated());
    }
}

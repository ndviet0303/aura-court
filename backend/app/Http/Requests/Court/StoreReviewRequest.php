<?php

namespace App\Http\Requests\Court;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reservation_code' => 'required|string|max:40',
            'booking_code' => 'prohibited',
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string|max:2000',
        ];
    }
}

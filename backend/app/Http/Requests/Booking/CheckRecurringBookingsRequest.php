<?php

namespace App\Http\Requests\Booking;

use App\Traits\ValidatesRecurringOccurrences;
use Illuminate\Foundation\Http\FormRequest;

/** FR-136, FR-137: the occurrences to check; checking holds no court time and no voucher (BR-172). */
class CheckRecurringBookingsRequest extends FormRequest
{
    use ValidatesRecurringOccurrences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->occurrenceRules();
    }

    public function messages(): array
    {
        return $this->occurrenceMessages();
    }
}

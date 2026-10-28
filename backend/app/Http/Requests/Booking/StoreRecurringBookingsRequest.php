<?php

namespace App\Http\Requests\Booking;

use App\Traits\ValidatesRecurringOccurrences;
use Illuminate\Foundation\Http\FormRequest;

/** FR-138: the occurrences to book, each with the amount payable the customer was shown (BR-171). */
class StoreRecurringBookingsRequest extends FormRequest
{
    use ValidatesRecurringOccurrences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [...$this->occurrenceRules(), 'occurrences.*.expected_total' => ['required', 'integer', 'min:0']];
    }

    public function messages(): array
    {
        return [...$this->occurrenceMessages(), 'occurrences.*.expected_total.required' => 'Vui lòng kiểm tra lại số tiền của từng lượt trước khi gửi.'];
    }
}

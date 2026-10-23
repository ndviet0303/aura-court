<?php

namespace App\Http\Requests\Customer;

use App\Domain\Customer\Rules\CustomerQuarter;
use Illuminate\Foundation\Http\FormRequest;

/** FR-110: the quarter to rank by (current quarter when absent) and a name or phone search. The controller checks the venue. */
class VenueCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quarter' => ['nullable', 'regex:/^20\d{2}-Q[1-4]$/'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['quarter.regex' => 'Quý phải có dạng YYYY-Qn, ví dụ 2026-Q4.'];
    }

    public function quarter(): CustomerQuarter
    {
        return CustomerQuarter::parse($this->validated('quarter'));
    }
}

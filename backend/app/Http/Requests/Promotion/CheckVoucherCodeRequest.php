<?php

namespace App\Http\Requests\Promotion;

use App\Traits\ValidatesVoucherQuote;
use Illuminate\Foundation\Http\FormRequest;

/** A private code checked for one court interval of the venue (design D10, D20); open to guests and throttled. */
class CheckVoucherCodeRequest extends FormRequest
{
    use ValidatesVoucherQuote;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->quoteRules(),
            'code' => ['required', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->quoteMessages(),
            'code.required' => 'Vui lòng nhập mã voucher.',
            'code.string' => 'Mã voucher không hợp lệ.',
            'code.max' => 'Mã voucher không hợp lệ.',
        ];
    }
}

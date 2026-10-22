<?php

namespace App\Http\Requests\Promotion;

use App\Traits\ValidatesVoucherQuote;
use Illuminate\Foundation\Http\FormRequest;

/** The public vouchers of a venue for one court interval (design D10); open to guests like availability. */
class ListVenueVouchersRequest extends FormRequest
{
    use ValidatesVoucherQuote;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->quoteRules();
    }

    public function messages(): array
    {
        return $this->quoteMessages();
    }
}

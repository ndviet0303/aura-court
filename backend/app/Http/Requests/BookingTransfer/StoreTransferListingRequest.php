<?php

namespace App\Http\Requests\BookingTransfer;

use Illuminate\Foundation\Http\FormRequest;

/** FR-140, BR-180: a note and a price per booking; everything else is read from the booking. */
class StoreTransferListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.reservation_id' => ['required', 'integer', 'distinct'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Vui lòng chọn ít nhất một booking để chuyển nhượng.',
            'items.*.price.required' => 'Vui lòng nhập giá chuyển nhượng cho từng booking.',
            'items.*.reservation_id.distinct' => 'Mỗi booking chỉ được chọn một lần.',
        ];
    }
}

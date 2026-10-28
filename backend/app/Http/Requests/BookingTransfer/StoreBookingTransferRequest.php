<?php

namespace App\Http\Requests\BookingTransfer;

use Illuminate\Foundation\Http\FormRequest;

/** FR-143, BR-183: who receives, which bookings and the price agreed for each. */
class StoreBookingTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['nullable', 'integer', 'exists:booking_transfer_listings,id'],
            'buyer_id' => ['required', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.reservation_id' => ['required', 'integer', 'distinct'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_id.exists' => 'Không tìm thấy tài khoản người nhận.',
            'items.required' => 'Vui lòng chọn ít nhất một booking để chuyển nhượng.',
            'items.*.price.required' => 'Vui lòng nhập giá đã thống nhất cho từng booking.',
            'items.*.reservation_id.distinct' => 'Mỗi booking chỉ được chọn một lần.',
        ];
    }
}

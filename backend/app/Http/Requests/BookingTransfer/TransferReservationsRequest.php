<?php

namespace App\Http\Requests\BookingTransfer;

use Illuminate\Foundation\Http\FormRequest;

/** The bookings of a listing a withdraw (optional: all) or a contact (required) is about. */
class TransferReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_ids' => [$this->routeIs('booking-transfers.listings.contact') ? 'required' : 'nullable', 'array', 'min:1', 'max:50'],
            'reservation_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return ['reservation_ids.required' => 'Vui lòng chọn ít nhất một lượt muốn nhận.'];
    }

    /** @return list<int>|null */
    public function reservationIds(): ?array
    {
        $ids = $this->validated('reservation_ids');

        return $ids === null ? null : array_values(array_map('intval', $ids));
    }
}

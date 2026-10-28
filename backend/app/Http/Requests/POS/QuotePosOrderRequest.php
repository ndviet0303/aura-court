<?php

namespace App\Http\Requests\POS;

use Illuminate\Foundation\Http\FormRequest;

class QuotePosOrderRequest extends FormRequest
{
    /** POS sales take no voucher or coupon (design D12): naming one is refused before anything is written, not ignored. */
    public const NO_VOUCHER = ['coupon_code' => 'prohibited', 'voucher_code' => 'prohibited', 'voucher_program_id' => 'prohibited'];

    public const NO_VOUCHER_MESSAGES = ['prohibited' => 'Voucher và mã giảm giá không áp dụng cho đơn bán hàng POS.'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.rental_duration_hours' => 'nullable|integer|min:1',
            'items.*.rental_hours' => 'nullable|integer|min:1',
            'items.*.vat_rate' => 'nullable|numeric|min:0|max:1',
            'customer_id' => 'nullable|integer',
            'reservation_id' => 'nullable|integer',
            'venue_id' => 'nullable|integer',
            'discount_amount' => 'nullable|integer|min:0',
            'payment_method' => 'nullable|in:cash,qr,wallet',
            'cash_tendered' => 'nullable|integer|min:0',
            'rounding_amount' => 'nullable|integer',
            ...self::NO_VOUCHER,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::NO_VOUCHER_MESSAGES;
    }
}

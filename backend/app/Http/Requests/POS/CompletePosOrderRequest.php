<?php

namespace App\Http\Requests\POS;

use Illuminate\Foundation\Http\FormRequest;

class CompletePosOrderRequest extends FormRequest
{
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
            'register_id' => 'nullable|string|max:80',
            'payment_method' => 'required|in:cash,qr,wallet,mixed',
            'allow_mixed_tender' => 'nullable|boolean',
            'tenders' => 'nullable|array',
            'tenders.*.method' => 'required_with:tenders|in:cash,qr,wallet',
            'tenders.*.amount' => 'required_with:tenders|integer|min:1',
            'tenders.*.cash_tendered' => 'nullable|integer|min:0',
            'tenders.*.change_due' => 'nullable|integer|min:0',
            'payment_reference' => 'nullable|string|max:160',
            'discount_amount' => 'nullable|integer|min:0',
            'cash_tendered' => 'nullable|integer|min:0',
            'rounding_amount' => 'nullable|integer',
            'offline' => 'nullable|boolean',
            ...QuotePosOrderRequest::NO_VOUCHER,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return QuotePosOrderRequest::NO_VOUCHER_MESSAGES;
    }
}

<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class RecurringSeriesPreviewRequest extends FormRequest
{
    /** Recurring series take no voucher or coupon and no points (design D12, D21): naming one is refused, not ignored. */
    public const NO_BENEFITS = ['coupon_code' => 'prohibited', 'voucher_code' => 'prohibited', 'voucher_program_id' => 'prohibited', 'loyalty_points' => 'prohibited'];

    public const NO_BENEFIT_MESSAGES = [
        'coupon_code.prohibited' => 'Voucher và mã giảm giá không áp dụng cho đặt sân định kỳ.',
        'voucher_code.prohibited' => 'Voucher và mã giảm giá không áp dụng cho đặt sân định kỳ.',
        'voucher_program_id.prohibited' => 'Voucher và mã giảm giá không áp dụng cho đặt sân định kỳ.',
        'loyalty_points.prohibited' => 'Đặt sân định kỳ không dùng điểm tích lũy.',
    ];

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
            'court_id' => 'required|integer|exists:courts,id',
            'frequency' => 'required|in:weekly,biweekly,monthly',
            'weekdays' => 'required_unless:frequency,monthly|array',
            'weekdays.*' => 'integer|min:1|max:7',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'until' => 'nullable|date|after_or_equal:starts_at',
            'occurrences' => 'nullable|integer|min:1|max:104',
            ...self::NO_BENEFITS,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::NO_BENEFIT_MESSAGES;
    }
}

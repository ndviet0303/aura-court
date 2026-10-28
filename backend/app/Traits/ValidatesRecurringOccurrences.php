<?php

namespace App\Traits;

use Closure;

/**
 * The occurrences of a recurring booking (module 08 D3): one court and a list of intervals, each checked and booked
 * on its own by the ordinary booking flow, which also judges the 30-minute grid, past times and availability. The cap
 * bounds one request; the report sets no limit. It sits outside Http/Requests, where every file must be a form request.
 */
trait ValidatesRecurringOccurrences
{
    public const MAX_OCCURRENCES = 100;

    /** @return array<string, mixed> */
    protected function occurrenceRules(): array
    {
        return [
            'court_id' => ['required', 'integer'],
            'occurrences' => ['required', 'array', 'min:1', 'max:'.self::MAX_OCCURRENCES, function (string $attribute, mixed $value, Closure $fail): void {
                // D8: one code per request, so a request cannot try many codes at once; codes match after trim and
                // upper case, as they are looked up.
                $codes = is_array($value) ? collect($value)->pluck('voucher_code')->map(fn (mixed $code): string => is_string($code) ? strtoupper(trim($code)) : '') : collect();
                if ($codes->filter()->unique()->count() > 1) {
                    $fail('Mỗi lần gửi chỉ dùng một mã voucher.');
                }
            }],
            'occurrences.*.starts_at' => ['required', 'date'],
            'occurrences.*.ends_at' => ['required', 'date', 'after:occurrences.*.starts_at'],
            'occurrences.*.voucher_program_id' => ['nullable', 'integer', 'prohibits:occurrences.*.voucher_code'],
            'occurrences.*.voucher_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    protected function occurrenceMessages(): array
    {
        return [
            'court_id.required' => 'Vui lòng chọn sân.',
            'occurrences.required' => 'Danh sách lượt đặt chưa có ngày nào.',
            'occurrences.min' => 'Danh sách lượt đặt chưa có ngày nào.',
            'occurrences.max' => 'Mỗi lần gửi tối đa '.self::MAX_OCCURRENCES.' lượt.',
            'occurrences.*.starts_at.required' => 'Vui lòng chọn giờ bắt đầu.',
            'occurrences.*.starts_at.date' => 'Giờ bắt đầu không hợp lệ.',
            'occurrences.*.ends_at.required' => 'Vui lòng chọn giờ kết thúc.',
            'occurrences.*.ends_at.date' => 'Giờ kết thúc không hợp lệ.',
            'occurrences.*.ends_at.after' => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'occurrences.*.voucher_program_id.prohibits' => 'Mỗi lượt chỉ dùng một voucher.',
        ];
    }
}

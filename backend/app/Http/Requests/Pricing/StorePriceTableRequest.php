<?php

namespace App\Http\Requests\Pricing;

use App\Models\PriceTable;
use App\Services\Reservation\VenueOperatingCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A price table with its first version (design D1, D15): each interval is [start, end) of the venue's local day
 * with 23:59 or 00:00 as an end meaning the end of the day, priced in whole VND above 0, on ISO weekdays (normal)
 * or dates (holiday). Intervals that share a day must not overlap.
 */
class StorePriceTableRequest extends FormRequest
{
    private const WEEKDAYS = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];

    public function authorize(): bool
    {
        // D25: only the owner of the venue's organization manages its prices.
        return (bool) $this->user()?->canManageVenue($this->route('venue'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in([PriceTable::KIND_NORMAL, PriceTable::KIND_HOLIDAY])],
            'effective_at' => ['required', 'date'],
            'intervals' => ['required', 'list', 'min:1', 'max:100'],
            'intervals.*.weekdays' => ['exclude_unless:kind,normal', 'required', 'list', 'min:1'],
            'intervals.*.weekdays.*' => ['exclude_unless:kind,normal', 'integer', 'between:1,7'],
            'intervals.*.dates' => ['exclude_unless:kind,holiday', 'required', 'list', 'min:1'],
            'intervals.*.dates.*' => ['exclude_unless:kind,holiday', 'date_format:Y-m-d'],
            'intervals.*.start' => ['required', 'date_format:H:i'],
            'intervals.*.end' => ['required', 'date_format:H:i'],
            // The column is a 32-bit integer.
            'intervals.*.price_per_hour' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function messages(): array
    {
        $price = 'Đơn giá của khoảng thứ :position phải là số nguyên VND lớn hơn 0.';

        return [
            'name.required' => 'Vui lòng nhập tên bảng giá.',
            'name.max' => 'Tên bảng giá tối đa 120 ký tự.',
            'kind.required' => 'Vui lòng chọn loại bảng giá.',
            'kind.in' => 'Loại bảng giá phải là Thông thường hoặc Ngày lễ.',
            'effective_at.required' => 'Vui lòng chọn thời điểm hiệu lực.',
            'effective_at.date' => 'Thời điểm hiệu lực không hợp lệ.',
            'intervals.required' => 'Bảng giá cần ít nhất một khoảng giờ.',
            'intervals.min' => 'Bảng giá cần ít nhất một khoảng giờ.',
            'intervals.list' => 'Danh sách khoảng giờ không hợp lệ.',
            'intervals.max' => 'Bảng giá có tối đa 100 khoảng giờ.',
            'intervals.*.weekdays.required' => 'Khoảng thứ :position cần ít nhất một thứ áp dụng.',
            'intervals.*.weekdays.min' => 'Khoảng thứ :position cần ít nhất một thứ áp dụng.',
            'intervals.*.weekdays.list' => 'Thứ áp dụng của khoảng thứ :position không hợp lệ.',
            'intervals.*.weekdays.*' => 'Thứ áp dụng của khoảng thứ :position không hợp lệ.',
            'intervals.*.dates.required' => 'Khoảng thứ :position cần ít nhất một ngày áp dụng.',
            'intervals.*.dates.min' => 'Khoảng thứ :position cần ít nhất một ngày áp dụng.',
            'intervals.*.dates.list' => 'Ngày áp dụng của khoảng thứ :position không hợp lệ.',
            'intervals.*.dates.*' => 'Ngày áp dụng của khoảng thứ :position không hợp lệ.',
            'intervals.*.start' => 'Giờ bắt đầu của khoảng thứ :position phải theo dạng HH:mm.',
            'intervals.*.end' => 'Giờ kết thúc của khoảng thứ :position phải theo dạng HH:mm.',
            'intervals.*.price_per_hour.required' => $price,
            'intervals.*.price_per_hour.integer' => $price,
            'intervals.*.price_per_hour.min' => $price,
            'intervals.*.price_per_hour.max' => 'Đơn giá của khoảng thứ :position quá lớn.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Only intervals whose times and days are well formed; the rules above report the others.
            $intervals = [];
            foreach ((array) $this->input('intervals', []) as $index => $interval) {
                [$start, $end] = [$interval['start'] ?? null, $interval['end'] ?? null];
                if (! is_int($index) || ! self::isClock($start) || ! self::isClock($end)) {
                    continue;
                }
                $from = VenueOperatingCalendar::minuteOfDay($start);
                $to = VenueOperatingCalendar::minuteOfDay($end, true);
                if ($to <= $from) {
                    $validator->errors()->add("intervals.{$index}.end", 'Giờ kết thúc của khoảng thứ '.($index + 1).' phải sau giờ bắt đầu.');

                    continue;
                }
                $intervals[$index] = [$from, $to, $this->days($interval)];
            }

            foreach ($intervals as $later => [$from, $to, $days]) {
                foreach ($intervals as $earlier => [$otherFrom, $otherTo, $otherDays]) {
                    $shared = array_values(array_intersect($days, $otherDays));
                    if ($earlier >= $later || $shared === [] || max($from, $otherFrom) >= min($to, $otherTo)) {
                        continue;
                    }
                    sort($shared);
                    $validator->errors()->add("intervals.{$later}", sprintf(
                        'Khoảng thứ %d (%s) chồng lấn khoảng thứ %d (%s) vào %s trong %s.',
                        $later + 1, self::range($from, $to), $earlier + 1, self::range($otherFrom, $otherTo),
                        implode(', ', array_map(fn (int|string $day): string => $this->dayLabel($day), $shared)),
                        self::range(max($from, $otherFrom), min($to, $otherTo)),
                    ));
                }
            }
        });
    }

    /**
     * The interval's well-formed days of the table's kind: ISO weekdays or Y-m-d dates.
     *
     * @param  array<string, mixed>  $interval
     * @return list<int|string>
     */
    private function days(array $interval): array
    {
        if ($this->input('kind') === PriceTable::KIND_NORMAL) {
            $weekdays = array_map(fn ($day): int|false => filter_var($day, FILTER_VALIDATE_INT), (array) ($interval['weekdays'] ?? []));

            return array_values(array_unique(array_filter($weekdays, fn (int|false $day): bool => $day !== false && $day >= 1 && $day <= 7)));
        }

        return array_values(array_unique(array_filter((array) ($interval['dates'] ?? []), fn ($date): bool => is_string($date)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) === 1 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]))));
    }

    private function dayLabel(int|string $day): string
    {
        return is_int($day) ? self::WEEKDAYS[$day] : implode('/', array_reverse(explode('-', $day)));
    }

    private static function isClock(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    private static function range(int $from, int $to): string
    {
        $clock = fn (int $minute): string => $minute === 1440 ? '23:59' : sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);

        return $clock($from).'–'.$clock($to);
    }
}

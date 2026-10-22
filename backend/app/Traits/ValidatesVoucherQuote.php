<?php

namespace App\Traits;

use App\Models\Venue;
use Illuminate\Validation\Rule;

/**
 * The court and interval a voucher is priced for (design D10), shared by the venue's voucher list and its private
 * code check: a court of the route's venue (another venue's court is refused without saying whose it is) and an
 * interval whose end follows its start. The engine then refuses, with 422, an interval it cannot price. It sits
 * outside Http/Requests, where every file must be a form request.
 */
trait ValidatesVoucherQuote
{
    /** @return array<string, list<mixed>> */
    protected function quoteRules(): array
    {
        $venue = $this->route('venue');

        return [
            'court_id' => ['required', 'integer', Rule::exists('courts', 'id')->where('venue_id', $venue instanceof Venue ? (int) $venue->id : 0)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    /** @return array<string, string> */
    protected function quoteMessages(): array
    {
        return [
            'court_id.required' => 'Vui lòng chọn sân.',
            'court_id.integer' => 'Sân không hợp lệ.',
            'court_id.exists' => 'Sân không thuộc cơ sở này.',
            'starts_at.required' => 'Vui lòng chọn giờ bắt đầu.',
            'starts_at.date' => 'Giờ bắt đầu không hợp lệ.',
            'ends_at.required' => 'Vui lòng chọn giờ kết thúc.',
            'ends_at.date' => 'Giờ kết thúc không hợp lệ.',
            'ends_at.after' => 'Giờ kết thúc phải sau giờ bắt đầu.',
        ];
    }
}

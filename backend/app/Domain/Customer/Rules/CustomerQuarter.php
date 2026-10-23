<?php

namespace App\Domain\Customer\Rules;

use Carbon\CarbonImmutable;

/** BR-141: a calendar quarter in venue time, as the range [from, to) that completion times are counted in. Attendance times are stored in the app timezone (Asia/Ho_Chi_Minh), so the range stays in it. */
final readonly class CustomerQuarter
{
    private function __construct(public string $label, public CarbonImmutable $from, public CarbonImmutable $to) {}

    /** "2026-Q4"; null is the current quarter in venue time. */
    public static function parse(?string $label): self
    {
        $now = CarbonImmutable::now('Asia/Ho_Chi_Minh');
        [$year, $quarter] = $label === null
            ? [$now->year, $now->quarter]
            : array_map('intval', explode('-Q', $label));
        $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1, 0, 0, 0, 'Asia/Ho_Chi_Minh');

        return new self(sprintf('%d-Q%d', $year, $quarter), $start, $start->addMonths(3));
    }
}

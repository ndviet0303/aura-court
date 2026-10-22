<?php

namespace App\Domain\Promotion\DTOs;

use App\Models\PromotionProgram;

/**
 * A committed update or stop. $termsChanged is true when a term that decides a pending booking's voucher changed
 * (PromotionProgram::PRICING_TERMS, the court set, or the stop): the bookings pending payment that hold its voucher
 * are to be re-priced after the commit (design D19, task 6.2), which is not wired yet.
 */
readonly class PromotionProgramChange
{
    public function __construct(
        public PromotionProgram $program,
        public bool $termsChanged,
    ) {}
}

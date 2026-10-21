<?php

namespace App\Domain\Pricing\DTOs;

/** Rental, discount and amount payable of one court interval, with the snapshot a booking keeps (design D17). */
readonly class RentalQuote
{
    /**
     * @param  array{parts: list<array{price_table_id: int, price_table_version_id: int, starts_at: string, ends_at: string, minutes: int, price_per_hour: int}>, voucher: null}  $snapshot
     */
    public function __construct(
        public int $rental,
        public int $discount,
        public int $total,
        public array $snapshot,
    ) {}
}

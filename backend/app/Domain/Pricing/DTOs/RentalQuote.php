<?php

namespace App\Domain\Pricing\DTOs;

/** Rental, discount and amount payable of one court interval, with the snapshot a booking keeps (design D17). */
readonly class RentalQuote
{
    /**
     * The voucher is null, or the applied program's terms on the application clock (ISO-8601 with offset) and its
     * discount; module 4 reads its ends_at for the payment deadline.
     *
     * @param  array{parts: list<array{price_table_id: int, price_table_version_id: int, starts_at: string, ends_at: string, minutes: int, price_per_hour: int}>, voucher: null|array{program_id: int, name: string, type: string, code: ?string, discount_kind: string, percent_bp: ?int, amount: ?int, max_discount: ?int, min_rental: ?int, starts_at: string, ends_at: string, discount: int}}  $snapshot
     */
    public function __construct(
        public int $rental,
        public int $discount,
        public int $total,
        public array $snapshot,
    ) {}
}

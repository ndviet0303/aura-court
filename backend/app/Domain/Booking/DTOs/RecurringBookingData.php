<?php

namespace App\Domain\Booking\DTOs;

use App\Http\Requests\Booking\CheckRecurringBookingsRequest;
use App\Http\Requests\Booking\StoreRecurringBookingsRequest;

/**
 * One court and the occurrences of a recurring booking, in the order the customer listed them (module 08 D3).
 *
 * @phpstan-type Occurrence array{starts_at: string, ends_at: string, voucher_program_id?: int|null, voucher_code?: string|null, expected_total?: int}
 */
readonly class RecurringBookingData
{
    /** @param  list<Occurrence>  $occurrences */
    public function __construct(public int $courtId, public array $occurrences) {}

    public static function from(CheckRecurringBookingsRequest|StoreRecurringBookingsRequest $request): self
    {
        return new self((int) $request->validated('court_id'), array_values($request->validated('occurrences')));
    }
}

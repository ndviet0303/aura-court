<?php

namespace App\Domain\Promotion\DTOs;

use App\Http\Requests\Promotion\CheckVoucherCodeRequest;
use App\Http\Requests\Promotion\ListVenueVouchersRequest;
use Carbon\CarbonImmutable;

/** The court interval a voucher is priced for (design D10), and the private code when one is checked. */
readonly class VoucherQuoteData
{
    public function __construct(
        public int $courtId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?string $code,
    ) {}

    public static function from(ListVenueVouchersRequest|CheckVoucherCodeRequest $request): self
    {
        $code = $request->validated('code');

        return new self(
            courtId: (int) $request->validated('court_id'),
            startsAt: CarbonImmutable::parse((string) $request->validated('starts_at')),
            endsAt: CarbonImmutable::parse((string) $request->validated('ends_at')),
            code: $code === null ? null : (string) $code,
        );
    }
}

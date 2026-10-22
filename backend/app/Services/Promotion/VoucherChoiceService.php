<?php

namespace App\Services\Promotion;

use App\Domain\Pricing\DTOs\RentalQuote;
use App\Domain\Pricing\DTOs\VoucherChoice;
use App\Domain\Promotion\DTOs\VoucherQuoteData;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Services\Pricing\RentalPriceCalculator;

/**
 * What the booking form offers before checkout (design D10, D20): the public vouchers that apply to a court interval
 * now, and the check of a private code. Neither holds nor consumes a use; the rules stay in VoucherEligibility and
 * the amounts in the engine, so the list and the check agree with checkout.
 */
class VoucherChoiceService
{
    public function __construct(
        private readonly VerifyEffectiveBookingEligibility $booking,
        private readonly RentalPriceCalculator $engine,
        private readonly VoucherEligibility $vouchers,
    ) {}

    /**
     * The rental without a voucher, and each applicable public voucher with its amounts, default first.
     *
     * @return array{rental: int, vouchers: list<RentalQuote>}
     */
    public function applicable(VoucherQuoteData $data): array
    {
        $court = $this->court($data);
        $base = $this->engine->quote($court, $data->startsAt, $data->endsAt, null);

        return ['rental' => $base->rental, 'vouchers' => $this->vouchers->applicablePublic($court, $base, now())];
    }

    /** The amounts with the private code, or VoucherNoLongerApplies with its reason and the voucher-less amounts. */
    public function check(VoucherQuoteData $data): RentalQuote
    {
        return $this->engine->quote($this->court($data), $data->startsAt, $data->endsAt, VoucherChoice::code((string) $data->code));
    }

    /** The court as public booking pages see it: 404 when it or its venue does not take bookings. */
    private function court(VoucherQuoteData $data): Court
    {
        return $this->booking->assertEligible($data->courtId, publicFacing: true);
    }
}

<?php

namespace App\Services\Social;

use App\Contracts\Repositories\VoucherUseRepositoryInterface;
use App\Models\Reservation;

/**
 * Maximum transfer price of a booking (BR-181): what the booking paid after every recorded discount, so nobody
 * transfers a discounted booking at the rental before discount.
 */
class ResalePriceCap
{
    public function __construct(private readonly VoucherUseRepositoryInterface $voucherUses) {}

    public function forReservation(Reservation $reservation): int
    {
        $voucher = $this->voucherUses->activeDiscounts([(int) $reservation->id])[$reservation->id] ?? 0;
        $rental = (int) $reservation->slots->sum('unit_price');

        // total_amount already nets legacy coupon, membership and points; min() never takes the voucher off twice.
        return max(0, min((int) $reservation->total_amount, $rental - $voucher));
    }
}

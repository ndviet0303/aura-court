<?php

namespace App\Services\Social;

use App\Contracts\Repositories\VoucherUseRepositoryInterface;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Maximum resale price of a slot (design D23): its share of what the booking pays after every recorded discount, so
 * nobody resells a discounted slot at the rental before discount. The listing and purchase checks, the listing
 * metadata and the sell tab all read it from here.
 */
class ResalePriceCap
{
    public function __construct(private readonly VoucherUseRepositoryInterface $voucherUses) {}

    public function forEntitlement(ReservationEntitlement $entitlement): int
    {
        $reservation = $entitlement->reservation;
        if ($reservation === null) {
            return 0;
        }
        [$paid, $rental] = $this->paid($reservation, $this->voucherUses->activeDiscounts([(int) $reservation->id])[$reservation->id] ?? 0);

        return $this->share($paid, $rental, (int) $entitlement->amount);
    }

    /**
     * A page of bookings as the API returns them, each entitlement with its max_resale_price; one voucher_uses query.
     *
     * @param  LengthAwarePaginator<int, Reservation>  $page
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function onPage(LengthAwarePaginator $page): LengthAwarePaginator
    {
        $discounts = $this->voucherUses->activeDiscounts($page->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all());

        return $page->through(function (Reservation $reservation) use ($discounts): array {
            [$paid, $rental] = $this->paid($reservation, $discounts[$reservation->id] ?? 0);

            return [...$reservation->toArray(), 'entitlements' => $reservation->entitlements
                ->map(fn (ReservationEntitlement $entitlement): array => [
                    ...$entitlement->toArray(),
                    'max_resale_price' => $this->share($paid, $rental, (int) $entitlement->amount),
                ])
                ->values()
                ->all()];
        });
    }

    /** @return array{0: int, 1: int} the amount P the booking pays and its rental T before discount */
    private function paid(Reservation $reservation, int $voucher): array
    {
        $rental = (int) $reservation->slots->sum('unit_price');

        // total_amount already nets legacy coupon, membership and points; min() never takes the voucher off twice.
        return [max(0, min((int) $reservation->total_amount, $rental - $voucher)), $rental];
    }

    /** An old multi-slot booking splits P by each slot's rental, fraction dropped; a one-slot booking gets P. */
    private function share(int $paid, int $rental, int $slotRental): int
    {
        return $rental > 0 ? intdiv($paid * $slotRental, $rental) : 0;
    }
}

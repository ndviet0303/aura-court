<?php

namespace App\Domain\BookingTransfer\Actions;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\BookingTransfer\DTOs\BookingTransferData;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Models\BookingTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FR-143, UC-9.7, BR-183, BR-184: the holder names the buyer, the bookings and the agreed prices. Every booking is
 * checked again; once created they are held for that buyer only, with no time limit, and the buyer is told.
 */
class CreateBookingTransfer
{
    public function __construct(
        private readonly BookingTransferRepositoryInterface $transfers,
        private readonly BookingTransferListingRepositoryInterface $listings,
        private readonly UserRepositoryInterface $users,
        private readonly TransferEligibility $eligibility,
        private readonly NotifyTransferParties $notify,
    ) {}

    public function execute(User $seller, BookingTransferData $data): BookingTransfer
    {
        $this->eligibility->assertActor($seller);
        $buyer = $this->users->findById($data->buyerId) ?? abort(404);
        $this->eligibility->assertBuyer($seller, $buyer);
        if ($data->listingId !== null && (int) $this->listings->find($data->listingId)?->seller_id !== (int) $seller->id) {
            TransferEligibility::fail('TRANSFER_FORBIDDEN', 'Bạn không phải người đăng tin chuyển nhượng này.', 403);
        }

        $transfer = DB::transaction(function () use ($seller, $buyer, $data): BookingTransfer {
            $reservations = $this->transfers->reservations(array_column($data->items, 'reservation_id'), true);
            $refusals = [];
            foreach ($data->items as $item) {
                $refusals[$item['reservation_id']] = $this->eligibility->refusal($reservations->get($item['reservation_id']), $seller, $item['price'], heldExcept: 0);
            }
            TransferEligibility::failIfRefused($refusals);

            $transfer = $this->transfers->create([
                'listing_id' => $data->listingId,
                'seller_id' => $seller->id,
                'buyer_id' => $buyer->id,
                'total' => array_sum(array_column($data->items, 'price')),
            ], array_map(fn (array $item): array => $item + [
                'organization_id' => (int) $reservations->get($item['reservation_id'])->organization_id,
            ], $data->items));
            $this->notify->execute($this->transfers->withDetails($transfer), NotifyTransferParties::CREATED);

            return $transfer;
        });

        return $this->transfers->withDetails($transfer);
    }
}

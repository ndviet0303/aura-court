<?php

namespace App\Domain\BookingTransfer\Actions;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Domain\BookingTransfer\DTOs\TransferListingData;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Models\BookingTransferListing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FR-139, FR-140, UC-9.1, UC-9.2: the holder offers some of their bookings, each at a price within what it paid.
 * Any booking refused refuses the whole listing with each reason, and nothing is posted (Bước 2). Posting holds
 * nothing (BR-182).
 */
class PostTransferListing
{
    public function __construct(
        private readonly BookingTransferListingRepositoryInterface $listings,
        private readonly BookingTransferRepositoryInterface $transfers,
        private readonly TransferEligibility $eligibility,
    ) {}

    public function execute(User $seller, TransferListingData $data): BookingTransferListing
    {
        $this->eligibility->assertActor($seller);

        return DB::transaction(function () use ($seller, $data): BookingTransferListing {
            $reservations = $this->transfers->reservations(array_column($data->items, 'reservation_id'), true);
            $refusals = [];
            foreach ($data->items as $item) {
                $refusals[$item['reservation_id']] = $this->eligibility->refusal($reservations->get($item['reservation_id']), $seller, $item['price'], listed: true);
            }
            TransferEligibility::failIfRefused($refusals);

            return $this->listings->create((int) $seller->id, $data->note, array_map(fn (array $item): array => $item + [
                'organization_id' => (int) $reservations->get($item['reservation_id'])->organization_id,
            ], $data->items));
        });
    }
}

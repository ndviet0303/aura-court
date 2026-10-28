<?php

namespace App\Domain\BookingTransfer\Actions;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Models\BookingTransferListing;
use App\Models\BookingTransferListingItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** D9: the seller takes listed bookings off the market, except those held for a buyer in a pending transaction. */
class WithdrawTransferListing
{
    public function __construct(private readonly BookingTransferListingRepositoryInterface $listings) {}

    /** @param  list<int>|null  $reservationIds  null: every listed booking not held */
    public function execute(BookingTransferListing $listing, User $actor, ?array $reservationIds): BookingTransferListing
    {
        if ((int) $listing->seller_id !== (int) $actor->id) {
            TransferEligibility::fail('TRANSFER_FORBIDDEN', 'Chỉ người đăng tin được rút lượt chuyển nhượng.', 403);
        }

        DB::transaction(function () use ($listing, $reservationIds): void {
            $items = $this->listings->lockListedItems($listing)->keyBy('reservation_id');
            if ($reservationIds === null) {
                $items = $items->reject(fn (BookingTransferListingItem $item): bool => (bool) $item->getAttribute('held'));
            } else {
                $refusals = [];
                foreach ($reservationIds as $id) {
                    $refusals[$id] = match (true) {
                        ! $items->has($id) => ['code' => 'TRANSFER_NOT_LISTED', 'message' => 'Lượt không còn được chào trên tin này.'],
                        (bool) $items->get($id)->getAttribute('held') => ['code' => 'TRANSFER_HELD', 'message' => 'Lượt đang được giữ trong một giao dịch khác.'],
                        default => null,
                    };
                }
                TransferEligibility::failIfRefused($refusals);
                $items = $items->whereIn('reservation_id', $reservationIds);
            }
            $this->listings->withdraw($items->pluck('id')->map(fn ($id): int => (int) $id)->values()->all());
        });

        return $this->listings->withDetails($listing);
    }
}

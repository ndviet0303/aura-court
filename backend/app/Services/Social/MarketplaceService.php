<?php

namespace App\Services\Social;

use App\Models\MarketplaceListing;
use App\Models\ReservationEntitlement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MarketplaceService
{
    public function __construct(private readonly ResalePriceCap $caps) {}

    /**
     * Lists one booked slot. A paid listing carries the seller's own receiving account; one without it
     * (seeded or older rows) stays visible but cannot be locked until the seller adds it.
     *
     * @param  array{bank_code: string, account_number: string, account_name: string}|null  $receiver
     */
    public function list(ReservationEntitlement $entitlement, User $seller, int $askingPrice, ?array $receiver = null): MarketplaceListing
    {
        abort_if($seller->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        abort_unless($entitlement->user_id === $seller->id, 403);
        abort_unless($entitlement->status === 'active', 422, 'Only an active entitlement can be listed.');

        $reservation = $entitlement->reservation;
        abort_if(
            $reservation && $reservation->isDisputed(),
            422,
            'Disputed reservations cannot be listed for transfer.'
        );

        $policy = $entitlement->court?->venue?->currentBookingPolicy;
        $transferAllowed = $policy ? (bool) data_get($policy->settings, 'transfer.transfer_allowed', true) : true;
        abort_unless($transferAllowed, 422, 'Transfers are not allowed by venue policy.');

        $cutoffHours = $policy ? (int) data_get($policy->settings, 'transfer.cutoff_hours', 2) : 2;
        $cutoffAt = $entitlement->starts_at?->copy()->subHours(max(1, $cutoffHours));
        abort_unless($cutoffAt && $cutoffAt->isFuture(), 422, 'A slot may be listed only before the venue policy cutoff.');
        // D23: never above what the booking paid for this slot after its discounts.
        $maxPrice = $this->caps->forEntitlement($entitlement);
        abort_unless($askingPrice >= 0 && $askingPrice <= $maxPrice, 422, 'Giá pass tối đa của giờ chơi này là '.number_format($maxPrice, 0, ',', '.').' ₫, bằng số tiền bạn đã trả sau giảm giá.');
        abort_if(
            $askingPrice === 0 && ! ($policy && data_get($policy->settings, 'transfer.free_transfer_allowed', false)),
            422,
            'Free transfer is not allowed by venue policy.'
        );

        $listing = new MarketplaceListing([
            'organization_id' => $entitlement->organization_id,
            'reservation_entitlement_id' => $entitlement->id,
            'seller_id' => $seller->id,
            'original_price' => $entitlement->amount,
            'asking_price' => $askingPrice,
            'fee_bps' => 0,
            'status' => 'listed',
            'cutoff_at' => $cutoffAt,
            'metadata' => ['max_resale_price' => $maxPrice],
        ]);
        if ($askingPrice > 0 && $receiver !== null) {
            $listing->setSellerReceiver($receiver);
        }

        try {
            $listing->save();

            return $listing;
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'marketplace_listings')) {
                abort(409, 'This entitlement is already listed.');
            }
            throw $exception;
        }
    }

    public function withdraw(MarketplaceListing $listing, User $seller): MarketplaceListing
    {
        abort_unless($listing->seller_id === $seller->id, 403);

        return DB::transaction(function () use ($listing): MarketplaceListing {
            $listing = MarketplaceListing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            // A lapsed buyer lock reads as released, so the seller may withdraw it.
            abort_unless($listing->effective_status === 'listed', 409, 'Listing is locked by a buyer or no longer listed.');
            $listing->update(['status' => 'withdrawn']);

            return $listing->fresh();
        });
    }
}

<?php

namespace App\Services\Social;

use App\Models\EntitlementTransfer;
use App\Models\MarketplaceListing;
use App\Models\ReservationEntitlement;
use App\Models\User;

/** Moves one listed slot to its buyer; the hold path and the resale receipt confirmer both go through here. */
class ResaleTransfer
{
    /** Rechecks, under row locks, everything that must still hold when ownership moves. */
    public function transferable(MarketplaceListing $listing, User $buyer): ReservationEntitlement
    {
        abort_if($listing->cutoff_at?->isPast(), 409, 'Transfer cutoff has passed.');
        abort_if($buyer->fresh()?->isRestricted(), 403, 'Tài khoản người mua đã bị khóa hoặc hạn chế quyền truy cập.');
        abort_if($listing->seller?->isRestricted(), 409, 'Tài khoản người bán đã bị khóa hoặc hạn chế quyền truy cập.');

        $entitlement = ReservationEntitlement::query()->withoutGlobalScope('tenant_isolation')
            ->whereKey($listing->reservation_entitlement_id)->lockForUpdate()->firstOrFail();
        abort_unless($entitlement->status === 'active', 409, 'The listed entitlement is no longer active.');
        abort_if((bool) $entitlement->reservation()->withoutGlobalScope('tenant_isolation')->lockForUpdate()->first()?->isDisputed(), 409, 'Reservation has an active dispute and cannot be transferred.');

        $policy = $entitlement->court?->venue?->currentBookingPolicy;
        abort_unless($policy ? (bool) data_get($policy->settings, 'transfer.transfer_allowed', true) : true, 409, 'Transfers are no longer allowed by venue policy.');

        return $entitlement;
    }

    /**
     * Moves exactly this slot's entitlement; other slots of the same booking stay with their holder. A paid sale was
     * collected by the venue owner ($paymentReference is its attempt), so the platform fee is always zero.
     */
    public function move(MarketplaceListing $listing, User $buyer, ReservationEntitlement $entitlement, ?string $paymentReference): EntitlementTransfer
    {
        $entitlement->update(['status' => 'transferred']);
        $buyerEntitlement = ReservationEntitlement::create([
            'organization_id' => $entitlement->organization_id,
            'reservation_id' => $entitlement->reservation_id,
            'reservation_slot_id' => $entitlement->reservation_slot_id,
            'user_id' => $buyer->id,
            'court_id' => $entitlement->court_id,
            'starts_at' => $entitlement->starts_at,
            'ends_at' => $entitlement->ends_at,
            'amount' => $entitlement->amount,
            'status' => 'active',
        ]);

        $reservation = $entitlement->reservation()->withoutGlobalScope('tenant_isolation')->first();
        if ($reservation && ! $reservation->entitlements()->where('status', 'active')->where('user_id', '!=', $buyer->id)->exists()) {
            $reservation->update(['user_id' => $buyer->id]);
        }

        $metadata = (array) ($listing->metadata ?? []);
        $transfer = EntitlementTransfer::create([
            'organization_id' => $listing->organization_id,
            'marketplace_listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'buyer_id' => $buyer->id,
            'seller_entitlement_id' => $entitlement->id,
            'buyer_entitlement_id' => $buyerEntitlement->id,
            'buyer_amount' => (int) $listing->asking_price,
            'marketplace_fee' => 0,
            'seller_refund' => 0,
            'buyer_payment_reference' => $paymentReference,
            'idempotency_key' => 'resale:listing:'.$listing->id,
            'status' => 'completed',
            'metadata' => [
                'settlement_mode' => 'owner_collected',
                'original_price' => (int) $entitlement->amount,
                'max_resale_price' => $metadata['max_resale_price'] ?? null,
                'consideration_amount' => (int) $listing->asking_price,
            ],
        ]);

        $listing->update(['status' => 'sold', 'sold_at' => now(), 'metadata' => $metadata]);

        return $transfer;
    }
}

<?php

namespace App\Services\Social;

use App\Services\Finance\FinancialPostingService;

use App\Models\EntitlementTransfer;
use App\Models\FinanceException;
use App\Models\MarketplaceListing;
use App\Models\ReservationEntitlement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceService
{
    public function __construct(private readonly FinancialPostingService $financials) {}

    public function list(ReservationEntitlement $entitlement, User $seller, int $askingPrice): MarketplaceListing
    {
        abort_unless($entitlement->user_id === $seller->id, 403);
        abort_unless($entitlement->status === 'active', 422, 'Only an active entitlement can be listed.');
        abort_unless($entitlement->starts_at?->greaterThan(now()->addHours(2)), 422, 'A slot may be listed only two hours before it starts.');
        abort_unless($askingPrice > 0 && $askingPrice <= $entitlement->amount, 422, 'Asking price cannot exceed the original price.');

        try {
            return MarketplaceListing::create([
                'organization_id' => $entitlement->organization_id,
                'reservation_entitlement_id' => $entitlement->id,
                'seller_id' => $seller->id,
                'original_price' => $entitlement->amount,
                'asking_price' => $askingPrice,
                'fee_bps' => 0,
                'status' => 'listed',
                'cutoff_at' => $entitlement->starts_at->copy()->subHours(2),
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'marketplace_listings')) {
                abort(409, 'This entitlement is already listed.');
            }
            throw $exception;
        }
    }

    public function purchase(MarketplaceListing $listing, User $buyer, string $idempotencyKey, string $buyerPaymentReference, ?string $sellerRefundReference = null, bool $sellerRefundAccepted = true): EntitlementTransfer
    {
        if ($listing->seller_id === $buyer->id) {
            throw ValidationException::withMessages(['listing' => 'A seller cannot buy their own listing.']);
        }
        return DB::transaction(function () use ($listing, $buyer, $idempotencyKey, $buyerPaymentReference, $sellerRefundReference, $sellerRefundAccepted): EntitlementTransfer {
            $listing = MarketplaceListing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            abort_unless($listing->status === 'listed' && $listing->cutoff_at?->isFuture(), 409, 'Listing is no longer available.');
            $fee = 0;
            $sellerRefund = $listing->asking_price;
            $transfer = EntitlementTransfer::firstOrCreate([
                'organization_id' => $listing->organization_id,
                'idempotency_key' => $idempotencyKey,
            ], [
                'marketplace_listing_id' => $listing->id,
                'seller_id' => $listing->seller_id,
                'buyer_id' => $buyer->id,
                'seller_entitlement_id' => $listing->reservation_entitlement_id,
                'buyer_amount' => $listing->asking_price,
                'marketplace_fee' => 0,
                'seller_refund' => $sellerRefund,
                'buyer_payment_reference' => $buyerPaymentReference,
                'seller_refund_reference' => $sellerRefundReference,
                'status' => 'pending',
            ]);
            if ($transfer->status !== 'pending') {
                return $transfer->fresh(['listing', 'buyerEntitlement']);
            }
            $listing->update(['status' => 'checkout']);

            if (! $sellerRefundAccepted) {
                $transfer->update(['status' => 'compensated', 'failure_reason' => 'Seller refund was not accepted; buyer payment requires compensation.']);
                $listing->update(['status' => 'listed']);
                FinanceException::create([
                    'organization_id' => $listing->organization_id,
                    'type' => 'marketplace_transfer_failed',
                    'severity' => 'high',
                    'status' => 'open',
                    'amount' => $listing->asking_price,
                    'reference' => (string) $transfer->id,
                    'description' => 'Buyer collection succeeded but seller refund was not accepted.',
                    'links' => ['listing_id' => $listing->id, 'transfer_id' => $transfer->id],
                    'detected_at' => now(),
                    'due_at' => now()->addMinutes(15),
                ]);
                return $transfer->fresh(['listing']);
            }

            $sellerEntitlement = ReservationEntitlement::query()->whereKey($listing->reservation_entitlement_id)->lockForUpdate()->firstOrFail();
            abort_unless($sellerEntitlement->status === 'active', 409, 'The listed entitlement is no longer active.');
            $sellerEntitlement->update(['status' => 'transferred']);
            $buyerEntitlement = ReservationEntitlement::create([
                'organization_id' => $listing->organization_id,
                'reservation_id' => $sellerEntitlement->reservation_id,
                'reservation_slot_id' => $sellerEntitlement->reservation_slot_id,
                'user_id' => $buyer->id,
                'court_id' => $sellerEntitlement->court_id,
                'starts_at' => $sellerEntitlement->starts_at,
                'ends_at' => $sellerEntitlement->ends_at,
                'amount' => $listing->asking_price,
                'status' => 'active',
            ]);
            $transfer->update(['buyer_entitlement_id' => $buyerEntitlement->id, 'status' => 'completed']);
            $listing->update(['status' => 'sold', 'sold_at' => now()]);
            $this->financials->postMarketplaceSale($listing->organization_id, $listing->id, $listing->asking_price, $sellerRefund, 0, $buyerPaymentReference);
            return $transfer->fresh(['listing', 'buyerEntitlement']);
        });
    }

    public function withdraw(MarketplaceListing $listing, User $seller): MarketplaceListing
    {
        abort_unless($listing->seller_id === $seller->id, 403);
        abort_unless($listing->status === 'listed', 409);
        $listing->update(['status' => 'withdrawn']);
        return $listing->fresh();
    }
}

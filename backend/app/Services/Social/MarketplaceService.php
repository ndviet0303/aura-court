<?php

namespace App\Services\Social;

use App\Models\EntitlementTransfer;
use App\Models\FinanceException;
use App\Models\MarketplaceListing;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\ReceivingConnection;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly OwnerPaymentAttemptService $attempts,
    ) {}

    public function list(ReservationEntitlement $entitlement, User $seller, int $askingPrice): MarketplaceListing
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
                'cutoff_at' => $cutoffAt,
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'marketplace_listings')) {
                abort(409, 'This entitlement is already listed.');
            }
            throw $exception;
        }
    }

    public function checkout(MarketplaceListing $listing, User $buyer): OwnerPaymentAttempt
    {
        abort_if($buyer->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        if ($listing->seller_id === $buyer->id) {
            throw ValidationException::withMessages(['listing' => 'A seller cannot buy their own listing.']);
        }
        abort_unless($listing->status === 'listed' && $listing->cutoff_at?->isFuture(), 409, 'Listing is no longer available.');

        $sellerEntitlement = $listing->entitlement;
        abort_unless($sellerEntitlement && $sellerEntitlement->status === 'active', 409, 'The listed entitlement is no longer active.');

        $reservation = $sellerEntitlement->reservation;
        abort_if($reservation && $reservation->isDisputed(), 409, 'Disputed reservations cannot be purchased.');

        $venue = $sellerEntitlement->court?->venue;
        $connection = ReceivingConnection::query()
            ->where('organization_id', $listing->organization_id)
            ->when($venue, fn ($q) => $q->where('venue_id', $venue->id))
            ->where('status', ReceivingConnection::STATUS_VERIFIED)
            ->first();

        abort_unless($connection !== null, 409, 'Venue does not have a verified direct receiving connection.');

        $existing = $this->attempts->latestForPurpose(
            'transfer_consideration',
            'listing:'.$listing->id.':buyer:'.$buyer->id
        );
        if ($existing && $existing->status === OwnerPaymentAttempt::STATUS_PENDING && $existing->expires_at?->isFuture()) {
            return $existing;
        }

        $reference = 'AC'.Str::upper(Str::random(18));
        $cutoff = $listing->cutoff_at;
        $defaultExpiry = now()->addMinutes(15);
        $expiresAt = $cutoff && $cutoff->lessThan($defaultExpiry) ? $cutoff : $defaultExpiry;

        return $this->attempts->createAttempt(
            $connection,
            $buyer,
            'transfer_consideration',
            'listing:'.$listing->id.':buyer:'.$buyer->id,
            $reference,
            $listing->asking_price,
            strtoupper((string) ($reservation?->currency ?? 'VND')),
            $expiresAt,
            'transfer:'.$listing->id.':buyer:'.$buyer->id.':'.now()->timestamp
        );
    }

    public function hasConfirmableListing(OwnerPaymentAttempt $attempt): bool
    {
        $listingId = $this->parseListingId((string) $attempt->purpose_id);
        if (! $listingId) {
            return false;
        }

        $listing = MarketplaceListing::query()
            ->withoutGlobalScope('tenant_isolation')
            ->find($listingId);

        if (! $listing || $listing->status !== 'listed') {
            return false;
        }

        if ((int) $listing->asking_price !== (int) $attempt->amount) {
            return false;
        }

        if ($listing->cutoff_at?->isPast()) {
            return false;
        }

        $entitlement = $listing->entitlement;
        if (! $entitlement || $entitlement->status !== 'active') {
            return false;
        }

        if ($entitlement->reservation?->isDisputed()) {
            return false;
        }

        return true;
    }

    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, ?Carbon $paidAt = null): void
    {
        $listingId = $this->parseListingId((string) $allocation->purpose_id);
        if (! $listingId) {
            return;
        }

        $listing = MarketplaceListing::query()
            ->withoutGlobalScope('tenant_isolation')
            ->find($listingId);

        if ($listing) {
            $metadata = (array) ($listing->metadata ?? []);
            $metadata['verified_buyer_allocation_id'] = $allocation->id;
            $metadata['verified_buyer_paid_at'] = ($paidAt ?? now())->toIso8601String();
            $listing->update(['metadata' => $metadata]);
        }
    }

    public function verifySellerReimbursementEvidence(MarketplaceListing $listing, string $reference): bool
    {
        $ref = trim($reference);
        if ($ref === '') {
            return false;
        }

        // 1. OwnerRefundObligation where owner reported execution or seller acknowledged
        $hasObligation = OwnerRefundObligation::query()
            ->where('organization_id', $listing->organization_id)
            ->where(function ($q) use ($ref) {
                $q->where('execution_reference', $ref)
                    ->orWhere('id', is_numeric($ref) ? (int) $ref : 0);
            })
            ->whereIn('status', [
                OwnerRefundObligation::STATUS_OWNER_REPORTED,
                OwnerRefundObligation::STATUS_ACKNOWLEDGED,
            ])
            ->exists();

        if ($hasObligation) {
            return true;
        }
        // 2. Verified OwnerPaymentReceipt matching reference
        $hasReceipt = OwnerPaymentReceipt::query()
            ->where('organization_id', $listing->organization_id)
            ->where(function ($q) use ($ref) {
                $q->where('reference_code', $ref)
                    ->orWhere('payment_code', $ref)
                    ->orWhere('provider_event_id', $ref);
            })
            ->whereIn('status', [
                OwnerPaymentReceipt::STATUS_ALLOCATED,
                OwnerPaymentReceipt::STATUS_PENDING,
                OwnerPaymentReceipt::STATUS_UNMATCHED,
            ])
            ->exists();
        if ($hasReceipt) {
            return true;
        }

        return false;
    }

    public function purchase(
        MarketplaceListing $listing,
        User $buyer,
        string $idempotencyKey,
        string $buyerPaymentReference,
        ?string $sellerRefundReference = null,
        bool $sellerRefundAccepted = true
    ): EntitlementTransfer {
        abort_if($buyer->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        if ($listing->seller_id === $buyer->id) {
            throw ValidationException::withMessages(['listing' => 'A seller cannot buy their own listing.']);
        }

        return DB::transaction(function () use ($listing, $buyer, $idempotencyKey, $buyerPaymentReference, $sellerRefundReference, $sellerRefundAccepted): EntitlementTransfer {
            $listing = MarketplaceListing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            abort_unless($listing->status === 'listed', 409, 'Listing is no longer available.');
            // Recheck seller account restriction at commit time
            $seller = $listing->seller;
            abort_if($seller && $seller->isRestricted(), 403, 'Tài khoản người bán đã bị khóa hoặc hạn chế quyền truy cập.');

            // Recheck buyer account restriction at commit time
            abort_if($buyer->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');

            // Recheck seller entitlement is still active
            $sellerEntitlement = ReservationEntitlement::query()->whereKey($listing->reservation_entitlement_id)->lockForUpdate()->firstOrFail();
            abort_unless($sellerEntitlement->status === 'active', 409, 'The listed entitlement is no longer active.');

            // Recheck dispute at commit time
            $reservation = $sellerEntitlement->reservation()->lockForUpdate()->firstOrFail();
            abort_if(
                $reservation->isDisputed(),
                409,
                'Reservation has an active dispute and cannot be transferred.'
            );

            // Recheck venue policy at commit time
            $policy = $sellerEntitlement->court?->venue?->currentBookingPolicy;
            $transferAllowed = $policy ? (bool) data_get($policy->settings, 'transfer.transfer_allowed', true) : true;
            abort_unless($transferAllowed, 409, 'Transfers are no longer allowed by venue policy.');

            // Recheck cutoff at commit time
            abort_if($listing->cutoff_at?->isPast(), 409, 'Transfer cutoff has passed.');

            // Buyer payment verification
            $isDirectOwner = config('features.flags.direct_owner_payments', false)
                || $reservation->payment_model === 'direct_owner_v1'
                || ReceivingConnection::query()
                    ->where('organization_id', $listing->organization_id)
                    ->where('status', ReceivingConnection::STATUS_VERIFIED)
                    ->exists();

            if ($isDirectOwner) {
                $buyerAllocation = OwnerPaymentAllocation::query()
                    ->where('organization_id', $listing->organization_id)
                    ->where('purpose', 'transfer_consideration')
                    ->where(function ($q) use ($listing) {
                        $q->where('purpose_id', 'listing:'.$listing->id)
                            ->orWhere('purpose_id', 'like', 'listing:'.$listing->id.':%');
                    })
                    ->where('amount', $listing->asking_price)
                    ->whereHas('attempt', function ($q) use ($buyerPaymentReference, $buyer) {
                        $q->where('reference', $buyerPaymentReference)
                            ->where('status', OwnerPaymentAttempt::STATUS_PAID)
                            ->where(function ($sq) use ($buyer) {
                                $sq->whereNull('user_id')
                                    ->orWhere('user_id', $buyer->id);
                            });
                    })
                    ->first();

                abort_unless(
                    $buyerAllocation !== null,
                    422,
                    'Unverified buyer payment reference: verified direct-owner payment receipt is required.'
                );
            }

            // Seller reimbursement verification
            $requiresSellerReimbursement = $isDirectOwner
                && ($policy ? (bool) data_get($policy->settings, 'transfer.seller_reimbursement_required', true) : true);

            $sellerReimbursementEvidenced = false;
            if (! $requiresSellerReimbursement) {
                $sellerReimbursementEvidenced = $sellerRefundAccepted;
            } elseif ($sellerRefundAccepted && ! empty($sellerRefundReference)) {
                $sellerReimbursementEvidenced = $this->verifySellerReimbursementEvidence($listing, (string) $sellerRefundReference);
            }

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
                'metadata' => [
                    'original_price' => $sellerEntitlement->amount,
                    'consideration_amount' => $listing->asking_price,
                ],
            ]);
            if ($transfer->status !== 'pending') {
                return $transfer->fresh(['listing', 'buyerEntitlement']);
            }
            $listing->update(['status' => 'checkout']);

            // If seller reimbursement could not be evidenced
            if (! $sellerReimbursementEvidenced) {
                $transfer->update([
                    'status' => 'compensated',
                    'failure_reason' => 'Seller reimbursement could not be evidenced; buyer payment requires compensation or owner resolution.',
                ]);
                $listing->update(['status' => 'listed']);
                FinanceException::create([
                    'organization_id' => $listing->organization_id,
                    'type' => 'marketplace_transfer_failed',
                    'severity' => 'high',
                    'status' => 'open',
                    'amount' => $listing->asking_price,
                    'reference' => (string) $transfer->id,
                    'description' => 'Buyer collection verified but seller reimbursement could not be evidenced.',
                    'links' => ['listing_id' => $listing->id, 'transfer_id' => $transfer->id],
                    'detected_at' => now(),
                    'due_at' => now()->addMinutes(15),
                ]);

                return $transfer->fresh(['listing']);
            }

            // Atomically revoke seller entitlement and check-in pass
            $sellerEntitlement->update(['status' => 'transferred']);

            // Atomically issue new active entitlement and check-in authority for buyer with original price preserved
            $buyerEntitlement = ReservationEntitlement::create([
                'organization_id' => $listing->organization_id,
                'reservation_id' => $sellerEntitlement->reservation_id,
                'reservation_slot_id' => $sellerEntitlement->reservation_slot_id,
                'user_id' => $buyer->id,
                'court_id' => $sellerEntitlement->court_id,
                'starts_at' => $sellerEntitlement->starts_at,
                'ends_at' => $sellerEntitlement->ends_at,
                'amount' => $sellerEntitlement->amount, // Giá gốc bất biến!
                'status' => 'active',
            ]);

            // Atomically switch reservation holder to buyer
            $reservation->update(['user_id' => $buyer->id]);

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

    private function parseListingId(string $purposeId): ?int
    {
        if (preg_match('/^listing:(\d+)/', $purposeId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}

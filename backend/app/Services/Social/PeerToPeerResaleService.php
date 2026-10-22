<?php

namespace App\Services\Social;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Models\EntitlementTransfer;
use App\Models\MarketplaceListing;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\AuraBookingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Peer-to-peer resale. The buyer pays the seller directly; Aura Court only locks the listing, shows the
 * seller's own account to the lock holder, and moves the entitlement once the seller confirms receipt.
 * No owner attempt, fee, reimbursement or journal is ever created here.
 */
class PeerToPeerResaleService
{
    public function __construct(private readonly ResaleListingState $view, private readonly ResalePriceCap $caps) {}

    private const LOCK_MINUTES = 15;

    private const LOCK_KEYS = ['locked_by', 'locked_until', 'reference', 'payment_reported_at'];

    public function lock(MarketplaceListing $listing, User $buyer, bool $acceptFree = false): array
    {
        abort_if($buyer->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        if ($listing->seller_id === $buyer->id) {
            throw ValidationException::withMessages(['listing' => 'A seller cannot buy their own listing.']);
        }

        $listing = DB::transaction(function () use ($listing, $buyer, $acceptFree): MarketplaceListing {
            // A purchase is a new booking: lock the court, then the venue, and recheck eligibility with the package (D9) before the listing.
            $court = Court::query()->whereKey($listing->entitlement()->withoutGlobalScope('tenant_isolation')->value('court_id'))->lockForUpdate()->firstOrFail();
            $venue = Venue::query()->whereKey($court->venue_id)->lockForUpdate()->firstOrFail();
            $eligibility = app(VerifyEffectiveBookingEligibility::class)->execute($court->setRelation('venue', $venue));
            // The slot keeps its recorded price, so an unpriced court does not block the purchase.
            abort_unless($eligibility['eligible'] || $eligibility['reason'] === 'COURT_UNPRICED', 409, $eligibility['message'] ?? '');
            $listing = $this->locked($listing->id);
            if ($listing->lockedBy() === $buyer->id) {
                return $listing;
            }
            if ($listing->status === 'checkout' && ! $listing->lockActive()) {
                $this->unlock($listing, 'expiry');
            }
            abort_unless($listing->status === 'listed' && $listing->cutoff_at?->isFuture(), 409, 'Listing is no longer available.');
            $entitlement = $this->transferable($listing, $buyer);
            $metadata = (array) ($listing->metadata ?? []);
            $metadata['buyer_ids'] = array_values(array_unique([...($metadata['buyer_ids'] ?? []), $buyer->id]));
            abort_if((int) $listing->asking_price > ($metadata['max_resale_price'] ?? $this->caps->forEntitlement($entitlement)), 409, 'Giá pass cao hơn số tiền người bán đã trả sau giảm giá nên không thể mua suất chơi này.');

            if ((int) $listing->asking_price === 0) {
                abort_unless($acceptFree, 422, 'A free transfer needs the buyer\'s explicit acceptance.');
                $listing->metadata = $metadata;
                $this->transfer($listing, $buyer, $entitlement);

                return $listing;
            }

            abort_unless($listing->sellerReceiver() !== null, 409, 'The seller has not added a receiving account to this listing.');
            $until = $listing->cutoff_at->copy()->min(now()->addMinutes(self::LOCK_MINUTES));
            $listing->update(['status' => 'checkout', 'metadata' => array_merge($metadata, [
                'locked_by' => $buyer->id,
                'locked_until' => $until->utc()->toIso8601String(),
                'reference' => 'RS'.Str::upper(Str::random(10)),
                'payment_reported_at' => null,
            ])]);

            return $listing;
        });

        $sold = $listing->status === 'sold';
        $this->notify($listing->seller_id, $sold ? 'resale_transferred' : 'resale_locked',
            $sold ? 'Suất chơi đã được nhận miễn phí' : 'Có người giữ chỗ mua suất chơi của bạn',
            $sold ? 'Người mua đã nhận suất chơi bạn nhượng miễn phí.' : 'Người mua sẽ chuyển tiền thẳng vào tài khoản của bạn. Hãy xác nhận khi đã nhận được tiền.');

        return $this->state($listing->fresh(), $buyer);
    }

    public function paymentSent(MarketplaceListing $listing, User $buyer): array
    {
        $listing = DB::transaction(function () use ($listing, $buyer): MarketplaceListing {
            $listing = $this->locked($listing->id);
            abort_unless($listing->lockedBy() === $buyer->id, 409, 'You do not hold the lock on this listing.');
            if (! data_get($listing->metadata, 'payment_reported_at')) {
                // A reported payment keeps the lock until the seller resolves it or the cutoff passes.
                $listing->update(['metadata' => array_merge((array) $listing->metadata, [
                    'payment_reported_at' => now()->utc()->toIso8601String(),
                    'locked_until' => $listing->cutoff_at->copy()->utc()->toIso8601String(),
                ])]);
            }

            return $listing;
        });

        $this->notify($listing->seller_id, 'resale_payment_reported', 'Người mua báo đã chuyển tiền',
            'Hãy kiểm tra tài khoản ngân hàng của bạn rồi xác nhận đã nhận tiền hoặc báo chưa nhận được.');

        return $this->state($listing->fresh(), $buyer);
    }

    public function release(MarketplaceListing $listing, User $user, ?string $reason = null): array
    {
        [$listing, $buyerId, $bySeller] = DB::transaction(function () use ($listing, $user, $reason): array {
            $listing = $this->locked($listing->id);
            $buyerId = $listing->lockedBy();
            abort_unless($buyerId !== null, 409, 'Listing is not locked.');
            $bySeller = $listing->seller_id === $user->id;
            if (! $bySeller) {
                abort_unless($buyerId === $user->id, 403);
                abort_if((bool) data_get($listing->metadata, 'payment_reported_at'), 409, 'Payment was reported; only the seller can release this lock.');
            }
            $this->unlock($listing, $bySeller ? 'seller' : 'buyer', $reason);

            return [$listing, $buyerId, $bySeller];
        });

        if ($bySeller) {
            $this->notify($buyerId, 'resale_released', 'Người bán báo chưa nhận được tiền',
                'Suất chơi chưa được chuyển cho bạn. Nếu bạn đã chuyển tiền, hãy mở khiếu nại để được hỗ trợ.');
        } else {
            $this->notify($listing->seller_id, 'resale_released', 'Người mua đã hủy giữ chỗ', 'Suất chơi của bạn đã trở lại chợ nhượng sân.');
        }

        return $this->state($listing->fresh(), $user);
    }

    public function confirmReceipt(MarketplaceListing $listing, User $seller): array
    {
        abort_unless($listing->seller_id === $seller->id, 403);

        $listing = DB::transaction(function () use ($listing): MarketplaceListing {
            $listing = $this->locked($listing->id);
            $buyerId = $listing->lockedBy();
            abort_unless($buyerId !== null, 409, 'No buyer holds this listing.');
            $buyer = User::query()->findOrFail($buyerId);
            $this->transfer($listing, $buyer, $this->transferable($listing, $buyer));

            return $listing;
        });

        $this->notify((int) data_get($listing->metadata, 'locked_by'), 'resale_transferred', 'Bạn đã nhận suất chơi',
            'Người bán đã xác nhận nhận tiền. Mã check-in mới của bạn đã sẵn sàng.');

        return $this->state($listing->fresh(), $seller);
    }

    public function state(MarketplaceListing $listing, User $user): array
    {
        return $this->view->for($listing, $user);
    }

    /** A resale support case: only the seller or a buyer who locked the listing may open one. */
    public function disputeCourt(int $listingId, User $user): ?Court
    {
        $listing = MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')->findOrFail($listingId);
        abort_unless($listing->seller_id === $user->id || in_array($user->id, data_get($listing->metadata, 'buyer_ids', []), true), 403, 'Unauthorized resale party.');

        return $listing->entitlement?->court;
    }

    /** Scheduled every minute: release lapsed locks and withdraw unsold listings past their cutoff. */
    public function sweep(): int
    {
        $count = 0;
        MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')->where('status', 'checkout')->pluck('id')
            ->each(function (int $id) use (&$count): void {
                $released = DB::transaction(function () use ($id): ?array {
                    $listing = $this->locked($id);
                    if ($listing->status !== 'checkout' || $listing->lockActive()) {
                        return null;
                    }
                    $released = [(int) data_get($listing->metadata, 'locked_by'), (bool) data_get($listing->metadata, 'payment_reported_at'), $listing->seller_id];
                    $this->unlock($listing, 'expiry');

                    return $released;
                });
                if ($released === null) {
                    return;
                }
                $count++;
                [$buyerId, $reported, $sellerId] = $released;
                if ($reported) {
                    $this->notify($buyerId, 'resale_expired', 'Người bán chưa xác nhận trước giờ chốt',
                        'Suất chơi không được chuyển cho bạn. Nếu bạn đã chuyển tiền, hãy mở khiếu nại để được hỗ trợ.');
                    $this->notify($sellerId, 'resale_expired', 'Giao dịch nhượng sân đã quá giờ chốt', 'Bạn chưa xác nhận khoản tiền người mua báo đã chuyển.');
                } else {
                    $this->notify($buyerId, 'resale_expired', 'Giữ chỗ đã hết hạn', 'Bạn chưa báo chuyển tiền trong thời gian giữ chỗ.');
                }
            });

        return $count + MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')
            ->where('status', 'listed')
            ->where('cutoff_at', '<=', now())
            ->update(['status' => 'withdrawn']);
    }

    private function locked(int $id): MarketplaceListing
    {
        return MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Rechecks, under row locks, everything that must still hold when ownership moves. */
    private function transferable(MarketplaceListing $listing, User $buyer): ReservationEntitlement
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

    /** Moves exactly this slot's entitlement; other slots of the same booking stay with their holder. */
    private function transfer(MarketplaceListing $listing, User $buyer, ReservationEntitlement $entitlement): void
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
        EntitlementTransfer::create([
            'organization_id' => $listing->organization_id,
            'marketplace_listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'buyer_id' => $buyer->id,
            'seller_entitlement_id' => $entitlement->id,
            'buyer_entitlement_id' => $buyerEntitlement->id,
            'buyer_amount' => (int) $listing->asking_price,
            'marketplace_fee' => 0,
            'seller_refund' => 0,
            'buyer_payment_reference' => $metadata['reference'] ?? null,
            'idempotency_key' => 'p2p:listing:'.$listing->id,
            'status' => 'completed',
            'metadata' => [
                'settlement_mode' => 'peer_to_peer',
                'original_price' => (int) $entitlement->amount,
                'max_resale_price' => $metadata['max_resale_price'] ?? null,
                'consideration_amount' => (int) $listing->asking_price,
                'payment_reported_at' => $metadata['payment_reported_at'] ?? null,
            ],
        ]);

        $listing->update(['status' => 'sold', 'sold_at' => now(), 'metadata' => $metadata]);
    }

    private function unlock(MarketplaceListing $listing, string $by, ?string $reason = null): void
    {
        $metadata = (array) ($listing->metadata ?? []);
        $metadata['last_release'] = [
            'by' => $by,
            'at' => now()->utc()->toIso8601String(),
            'buyer_id' => (int) ($metadata['locked_by'] ?? 0),
            'payment_reported' => (bool) ($metadata['payment_reported_at'] ?? false),
            'reason' => $reason,
        ];
        foreach (self::LOCK_KEYS as $key) {
            unset($metadata[$key]);
        }
        $listing->update(['status' => $listing->cutoff_at?->isFuture() ? 'listed' : 'withdrawn', 'metadata' => $metadata]);
    }

    private function notify(?int $userId, string $event, string $title, string $body): void
    {
        if ($userId) {
            User::query()->find($userId)?->notify(new AuraBookingNotification($event, $title, $body, '/resale'));
        }
    }
}

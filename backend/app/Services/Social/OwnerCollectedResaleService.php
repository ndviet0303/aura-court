<?php

namespace App\Services\Social;

use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Models\MarketplaceListing;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\AuraBookingNotification;
use App\Services\Finance\VenueCreditService;
use App\Services\Payment\OwnerPaymentAttemptService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Owner-collected resale (resale-venue-credit D1, D4). A hold opens a `resale` owner-payment attempt on the venue's
 * verified receiving connection; the verified receipt moves the slot and issues the seller venue credit. Neither the
 * buyer nor the seller can move or block a transfer by reporting anything.
 */
class OwnerCollectedResaleService
{
    public function __construct(
        private readonly ResaleListingState $view,
        private readonly ResalePriceCap $caps,
        private readonly VerifyEffectiveBookingEligibility $eligibility,
        private readonly ResaleTransfer $transfers,
        private readonly OrganizationLockRepositoryInterface $locks,
    ) {}

    private const LOCK_MINUTES = 15;

    private const LOCK_KEYS = ['locked_by', 'locked_until', 'reference'];

    public function lock(MarketplaceListing $listing, User $buyer, bool $acceptFree = false): array
    {
        abort_if($buyer->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        if ($listing->seller_id === $buyer->id) {
            throw ValidationException::withMessages(['listing' => 'A seller cannot buy their own listing.']);
        }

        $listing = DB::transaction(function () use ($listing, $buyer, $acceptFree): MarketplaceListing {
            // One hold per buyer: their row serializes their holds (D4).
            $this->locks->lockUserForUpdate($buyer->id);
            $venue = $this->lockSlot($listing);
            $connection = $venue->current_receiving_connection_id ? ReceivingConnection::query()->find($venue->current_receiving_connection_id) : null;
            abort_if((int) $listing->asking_price > 0 && $connection === null, 409, 'Cơ sở chưa có tài khoản nhận tiền đã xác minh nên chưa mua được suất này.');
            $listing = $this->locked($listing->id);
            if ($listing->lockedBy() === $buyer->id) {
                return $listing;
            }
            if ($listing->status === 'checkout' && ! $listing->lockActive()) {
                $this->unlock($listing, 'expiry');
            }
            abort_unless($listing->status === 'listed' && $listing->cutoff_at?->isFuture(), 409, 'Listing is no longer available.');
            abort_if($this->holdsAnother($buyer, $listing), 409, 'Bạn đang giữ chỗ một suất khác. Hãy thanh toán hoặc huỷ giữ chỗ đó trước.');
            $entitlement = $this->transfers->transferable($listing, $buyer);
            $metadata = (array) ($listing->metadata ?? []);
            $metadata['buyer_ids'] = array_values(array_unique([...($metadata['buyer_ids'] ?? []), $buyer->id]));
            abort_if((int) $listing->asking_price > ($metadata['max_resale_price'] ?? $this->caps->forEntitlement($entitlement)), 409, 'Giá pass cao hơn số tiền người bán đã trả sau giảm giá nên không thể mua suất chơi này.');

            if ((int) $listing->asking_price === 0) {
                abort_unless($acceptFree, 422, 'A free transfer needs the buyer\'s explicit acceptance.');
                $listing->metadata = $metadata;
                $this->transfers->move($listing, $buyer, $entitlement, null);

                return $listing;
            }

            $until = $listing->cutoff_at->copy()->min(now()->addMinutes(self::LOCK_MINUTES));
            $reference = 'AC'.Str::upper(Str::random(18));
            $listing->update(['status' => 'checkout', 'metadata' => array_merge($metadata, [
                'locked_by' => $buyer->id,
                'locked_until' => $until->utc()->toIso8601String(),
                'reference' => $reference,
            ])]);
            app(OwnerPaymentAttemptService::class)->createAttempt($connection, $buyer, 'resale', (string) $listing->id, $reference,
                (int) $listing->asking_price, 'VND', $until, 'resale:'.$listing->id.':'.$reference);

            return $listing;
        });

        $sold = $listing->status === 'sold';
        $this->notify($listing->seller_id, $sold ? 'resale_transferred' : 'resale_locked',
            $sold ? 'Suất chơi đã được nhận miễn phí' : 'Có người giữ chỗ mua suất chơi của bạn',
            $sold ? 'Người mua đã nhận suất chơi bạn nhượng miễn phí.' : 'Người mua đang thanh toán vào tài khoản của chủ sân. Khi tiền về, suất chơi sẽ chuyển và bạn nhận credit tại sân.');

        return $this->state($listing->fresh(), $buyer);
    }

    /** Only the buyer cancels their hold; the seller cannot release a buyer who may be paying (D4). */
    public function release(MarketplaceListing $listing, User $user, ?string $reason = null): array
    {
        $listing = DB::transaction(function () use ($listing, $user, $reason): MarketplaceListing {
            $listing = $this->locked($listing->id);
            abort_unless($listing->lockedBy() !== null, 409, 'Listing is not locked.');
            abort_unless($listing->lockedBy() === $user->id, 403, 'Chỉ người đang giữ chỗ mới huỷ được giữ chỗ này.');
            $this->unlock($listing, 'buyer', $reason);
            $this->expireAttemptAfterCommit($listing->id);

            return $listing;
        });

        $this->notify($listing->seller_id, 'resale_released', 'Người mua đã hủy giữ chỗ', 'Suất chơi của bạn đã trở lại chợ nhượng sân.');

        return $this->state($listing->fresh(), $user);
    }

    /** Receipt processing calls this after locking the attempt: listing, then entitlement and booking (D12 order). */
    public function hasConfirmableResale(OwnerPaymentAttempt $attempt): bool
    {
        $listing = MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')->whereKey((int) $attempt->purpose_id)->lockForUpdate()->first();
        $buyer = $attempt->user_id ? User::query()->find($attempt->user_id) : null;
        if (! $listing || ! $buyer || $listing->status !== 'checkout' || (int) data_get($listing->metadata, 'locked_by') !== (int) $buyer->id
            || data_get($listing->metadata, 'reference') !== $attempt->reference || (int) $listing->asking_price !== (int) $attempt->amount) {
            return false;
        }
        try {
            $this->transfers->transferable($listing, $buyer);
        } catch (HttpExceptionInterface) {
            return false;
        }

        return true;
    }

    /** The owner received exactly the asking price: the slot moves and the seller is owed it as venue credit. */
    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, CarbonInterface $paidAt): void
    {
        $listing = $this->locked((int) $allocation->purpose_id);
        $attempt = OwnerPaymentAttempt::query()->findOrFail($allocation->payment_attempt_id);
        $buyer = User::query()->findOrFail($attempt->user_id);
        $transfer = $this->transfers->move($listing, $buyer, $this->transfers->transferable($listing, $buyer), $attempt->reference);
        $amount = (int) $listing->asking_price;
        app(VenueCreditService::class)->issue($listing->organization_id, $listing->seller_id, $amount, 'entitlement_transfer', $transfer->id,
            'receipt:'.$allocation->payment_receipt_id, ['marketplace_listing_id' => $listing->id, 'paid_at' => $paidAt->toIso8601String()]);

        $organization = (string) $listing->organization()->value('name');
        DB::afterCommit(function () use ($listing, $buyer, $amount, $organization): void {
            $this->notify($listing->seller_id, 'resale_transferred', 'Suất chơi đã bán · +'.number_format($amount, 0, ',', '.').' credit',
                "Bạn nhận {$amount} credit tại {$organization}. Credit dùng để đặt sân, mua đồ tại quầy hoặc đổi tiền mặt tại quầy.");
            $this->notify($buyer->id, 'resale_transferred', 'Bạn đã nhận suất chơi', 'Chủ sân đã nhận tiền. Mã check-in mới của bạn đã sẵn sàng.');
        });
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

    /** Scheduled every minute: release lapsed holds and withdraw unsold listings past their cutoff. */
    public function sweep(): int
    {
        $count = 0;
        MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')->where('status', 'checkout')->pluck('id')
            ->each(function (int $id) use (&$count): void {
                $buyerId = DB::transaction(function () use ($id): ?int {
                    $listing = $this->locked($id);
                    if ($listing->status !== 'checkout' || $listing->lockActive()) {
                        return null;
                    }
                    $buyerId = (int) data_get($listing->metadata, 'locked_by');
                    $this->unlock($listing, 'expiry');
                    $this->expireAttemptAfterCommit($id);

                    return $buyerId;
                });
                if ($buyerId !== null) {
                    $count++;
                    $this->notify($buyerId, 'resale_expired', 'Giữ chỗ đã hết hạn', 'Bạn chưa thanh toán trong thời gian giữ chỗ nên suất chơi đã trở lại chợ.');
                }
            });

        return $count + MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')
            ->where('status', 'listed')
            ->where('cutoff_at', '<=', now())
            ->update(['status' => 'withdrawn']);
    }

    /** A purchase is a new booking: lock the court, then the venue, and recheck eligibility with the package (D9). */
    private function lockSlot(MarketplaceListing $listing): Venue
    {
        // ponytail: the court comes from an unlocked read; recheck it under the entitlement lock once D8 move-court can move a listed slot.
        $slot = $listing->entitlement()->withoutGlobalScope('tenant_isolation')->firstOrFail();
        $court = Court::query()->whereKey($slot->court_id)->lockForUpdate()->firstOrFail();
        $venue = Venue::query()->whereKey($court->venue_id)->lockForUpdate()->firstOrFail();
        $this->eligibility->assertResalable($court->setRelation('venue', $venue), $slot->starts_at, $slot->ends_at);

        return $venue;
    }

    private function holdsAnother(User $buyer, MarketplaceListing $listing): bool
    {
        return MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')->where('status', 'checkout')->whereKeyNot($listing->id)
            ->where('metadata->locked_by', (string) $buyer->id)->where('metadata->locked_until', '>', now()->utc()->toIso8601String())->exists();
    }

    private function locked(int $id): MarketplaceListing
    {
        return MarketplaceListing::query()->withoutGlobalScope('tenant_isolation')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** The hold path never updates an attempt while it holds the listing lock; the receipt path locks them the other way. */
    private function expireAttemptAfterCommit(int $listingId): void
    {
        DB::afterCommit(fn () => app(OwnerPaymentAttemptService::class)->expirePendingForPurpose('resale', (string) $listingId));
    }

    private function unlock(MarketplaceListing $listing, string $by, ?string $reason = null): void
    {
        $metadata = (array) ($listing->metadata ?? []);
        $metadata['last_release'] = [
            'by' => $by,
            'at' => now()->utc()->toIso8601String(),
            'buyer_id' => (int) ($metadata['locked_by'] ?? 0),
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

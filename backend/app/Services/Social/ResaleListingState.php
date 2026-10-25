<?php

namespace App\Services\Social;

use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\VenueCreditEntry;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\ReservationPaymentService;

/** Read side of owner-collected resale. Every read treats a lapsed hold as released; no seller account exists to show. */
class ResaleListingState
{
    public function __construct(private readonly OwnerPaymentAttemptService $attempts, private readonly ReservationPaymentService $payments) {}

    /** The listing as one party sees it; only the hold's buyer sees the owner's QR (D4). */
    public function for(MarketplaceListing $listing, User $user): array
    {
        $metadata = (array) ($listing->metadata ?? []);
        $isSeller = $listing->seller_id === $user->id;
        $holdsLock = $listing->lockedBy() === $user->id;
        abort_unless($isSeller || $holdsLock || in_array($user->id, $metadata['buyer_ids'] ?? [], true), 404);

        $reference = (string) ($metadata['reference'] ?? '');
        $attempt = $holdsLock ? $this->attempts->latestForPurpose('resale', (string) $listing->id) : null;
        $transfer = $listing->status === 'sold' ? $listing->transfers()->where('status', 'completed')->latest('id')->first() : null;
        $credit = $transfer && $isSeller ? VenueCreditEntry::query()->withoutGlobalScope('tenant_isolation')
            ->where('source_type', 'entitlement_transfer')->where('source_id', (string) $transfer->id)->where('type', 'issue')->value('balance_delta') : null;
        $lastRelease = $metadata['last_release'] ?? null;
        $seesRelease = $lastRelease && ($isSeller || (int) ($lastRelease['buyer_id'] ?? 0) === $user->id);

        return [
            'listing' => $listing->loadMissing(['entitlement.court.venue', 'seller']),
            'role' => $isSeller ? 'seller' : 'buyer',
            'status' => $listing->effective_status,
            'lock' => $listing->lockActive() && ($isSeller || $holdsLock) ? [
                'locked_until' => $metadata['locked_until'],
                'reference' => $holdsLock ? $reference : null,
                'held_by_me' => $holdsLock,
            ] : null,
            'payment' => $attempt && $attempt->reference === $reference ? $this->payments->instructions($attempt) : null,
            'transfer' => $transfer && ($isSeller || $transfer->buyer_id === $user->id) ? [
                'id' => $transfer->id,
                'status' => $transfer->status,
                'buyer_amount' => (int) $transfer->buyer_amount,
                'marketplace_fee' => (int) $transfer->marketplace_fee,
                'seller_refund' => (int) $transfer->seller_refund,
                'settlement_mode' => data_get($transfer->metadata, 'settlement_mode'),
            ] : null,
            'credit_issued' => $credit !== null ? (int) $credit : null,
            'last_release' => $seesRelease ? [
                'by' => $lastRelease['by'],
                'at' => $lastRelease['at'],
                'reason' => $lastRelease['reason'] ?? null,
            ] : null,
        ];
    }
}

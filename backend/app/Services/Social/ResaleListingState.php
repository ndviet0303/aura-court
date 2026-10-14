<?php

namespace App\Services\Social;

use App\Models\MarketplaceListing;
use App\Models\User;
use App\Services\Payment\VietQrImage;

/** Read side of peer-to-peer resale. Every read treats a lapsed lock as released. */
class ResaleListingState
{
    /** The listing as one party sees it; the seller's account is shown only to the seller and the lock holder. */
    public function for(MarketplaceListing $listing, User $user): array
    {
        $metadata = (array) ($listing->metadata ?? []);
        $isSeller = $listing->seller_id === $user->id;
        $holdsLock = $listing->lockedBy() === $user->id;
        abort_unless($isSeller || $holdsLock || in_array($user->id, $metadata['buyer_ids'] ?? [], true), 404);

        $receiver = $isSeller || $holdsLock ? $listing->sellerReceiver() : null;
        $active = $listing->lockActive() && $receiver !== null;
        $reference = (string) ($metadata['reference'] ?? '');
        $transfer = $listing->status === 'sold' ? $listing->transfers()->where('status', 'completed')->latest('id')->first() : null;
        $lastRelease = $metadata['last_release'] ?? null;
        $seesRelease = $lastRelease && ($isSeller || (int) ($lastRelease['buyer_id'] ?? 0) === $user->id);

        return [
            'listing' => $listing->loadMissing(['entitlement.court.venue', 'seller']),
            'role' => $isSeller ? 'seller' : 'buyer',
            'status' => $listing->effective_status,
            'lock' => $listing->lockActive() && ($isSeller || $holdsLock) ? [
                'locked_until' => $metadata['locked_until'],
                'payment_reported_at' => $metadata['payment_reported_at'] ?? null,
                'reference' => $reference,
                'held_by_me' => $holdsLock,
            ] : null,
            'receiver' => $receiver,
            'payment' => $active ? [
                'amount' => (int) $listing->asking_price,
                'currency' => 'VND',
                'reference' => $reference,
                'transfer_content' => $reference,
                'qr_url' => VietQrImage::url($receiver['bank_code'], $receiver['account_number'], $receiver['account_name'], (int) $listing->asking_price, $reference),
            ] : null,
            'transfer' => $transfer && ($isSeller || $transfer->buyer_id === $user->id) ? [
                'id' => $transfer->id,
                'status' => $transfer->status,
                'buyer_amount' => (int) $transfer->buyer_amount,
                'marketplace_fee' => (int) $transfer->marketplace_fee,
                'seller_refund' => (int) $transfer->seller_refund,
                'settlement_mode' => data_get($transfer->metadata, 'settlement_mode'),
            ] : null,
            'last_release' => $seesRelease ? [
                'by' => $lastRelease['by'],
                'at' => $lastRelease['at'],
                'payment_reported' => (bool) ($lastRelease['payment_reported'] ?? false),
                'reason' => $lastRelease['reason'] ?? null,
            ] : null,
        ];
    }
}
